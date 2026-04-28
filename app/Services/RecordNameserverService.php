<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RecordNameserverService
{
    private const int TIMEOUT_SECONDS_REQUEST = 10;
    private const int LIMIT_DOMAINS_PER_RUN = 300;
    private const int CHUNK_SIZE = 100;

    public function handle(): void
    {
        $domains = $this->getDomains();
        if (empty($domains)) {
            return;
        }

        foreach (array_chunk($domains, self::CHUNK_SIZE) as $chunk) {
            $this->processChunk($chunk);
        }
    }

    private function processChunk(array $chunk): void
    {
        $responses = Http::pool(function (Pool $pool) use ($chunk) {
            foreach ($chunk as $d) {
                $domain = $d->name . $d->zone;

                // RDAP dan DNS sekaligus dalam 1 pool — concurrent
                $pool->as('rdap_' . $d->id)
                    ->withOptions(['verify' => false])
                    ->timeout(self::TIMEOUT_SECONDS_REQUEST)
                    ->connectTimeout(self::TIMEOUT_SECONDS_REQUEST)
                    ->get("https://rdap.pandi.id/rdap/domain/$domain");

                $pool->as('dns_' . $d->id)
                    ->withOptions(['verify' => false])
                    ->timeout(self::TIMEOUT_SECONDS_REQUEST)
                    ->connectTimeout(self::TIMEOUT_SECONDS_REQUEST)
                    ->withHeaders(['Accept' => 'application/dns-json'])
                    ->get("https://1.1.1.1/dns-query?name=$domain&type=A");
            }
        });

        DB::transaction(function () use ($chunk, $responses) {
            foreach ($chunk as $d) {
                $rdap = $responses['rdap_' . $d->id] ?? null;
                $dns = $responses['dns_' . $d->id] ?? null;

                $names = $this->getNames($rdap);
                $ip = $this->getIpFromDns($dns);

                $this->create([
                    'domain_id' => $d->id,
                    'name' => empty($names) ? null : implode(',', $names),
                    'ip' => $ip,
                    'retry' => 0,
                ]);
            }
        });
    }

    private function fortestonlyprocessChunk(array $chunk): void
    {
        // Ambil 1 domain saja dulu untuk debug
        $d = $chunk[0];
        $domain = $d->name . $d->zone;

        $rdap = Http::timeout(self::TIMEOUT_SECONDS_REQUEST)
            ->withOptions(['verify' => false])
            ->get("https://rdap.pandi.id/rdap/domain/$domain");

        $dns = Http::timeout(self::TIMEOUT_SECONDS_REQUEST)
            ->withOptions(['verify' => false])
            ->withHeaders(['Accept' => 'application/dns-json'])
            ->get("https://1.1.1.1/dns-query?name=$domain&type=A");

        \Log::debug('DEBUG RDAP', [
            'domain' => $domain,
            'status' => $rdap instanceof Throwable ? 'THROWABLE: ' . $rdap->getMessage() : $rdap->status(),
            'body' => $rdap instanceof Throwable ? null : $rdap->json(),
        ]);

        \Log::debug('DEBUG DNS', [
            'domain' => $domain,
            'status' => $dns instanceof Throwable ? 'THROWABLE: ' . $dns->getMessage() : $dns->status(),
            'body' => $dns instanceof Throwable ? null : $dns->json(),
        ]);

        dd('cek log laravel'); // stop di sini
    }

    private function getDomains(): array
    {
        $sql = <<<SQL
            SELECT d.id, d.name, d.zone
            FROM domains d
            LEFT JOIN nameservers n
                ON d.id = n.domain_id
                AND ((n.name IS NOT NULL AND n.ip IS NOT NULL) OR n.retry >= 3)
            WHERE d.status = 'active'
                AND d.zone IN ('.go.id', '.desa.id')
                AND n.domain_id IS NULL
            LIMIT ?
        SQL;

        return DB::select($sql, [self::LIMIT_DOMAINS_PER_RUN]);
    }

    private function getNames(mixed $response): array
    {
        // Pool tidak throw — gagal dikembalikan sebagai Throwable
        if ($response instanceof Throwable || $response === null) {
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        // Struktur standar RDAP RFC 9083:
        // { "nameservers": [ { "ldhName": "ns1.example.com" }, ... ] }
        $nameservers = $response->json('nameservers');
        if (empty($nameservers) || !is_array($nameservers)) {
            return [];
        }

        $names = [];
        foreach ($nameservers as $ns) {
            $name = $ns['ldhName'] ?? null;
            if (!empty($name)) {
                $names[] = strtolower(trim($name));
            }
        }

        return $names;
    }

    private function getIpFromDns(mixed $response): ?string
    {
        if ($response instanceof Throwable || $response === null) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        // Cloudflare DoH response: { "Answer": [ { "type": 1, "data": "x.x.x.x" } ] }
        $answers = $response->json('Answer') ?? [];
        foreach ($answers as $answer) {
            if (($answer['type'] ?? null) === 1 && !empty($answer['data'])) {
                return (string) $answer['data'];
            }
        }

        return null;
    }

    private function create(array $inputs): void
    {
        $sql = <<<SQL
            INSERT INTO nameservers (domain_id, name, ip, retry)
            VALUES (:domain_id, :name, :ip, :retry)
            ON DUPLICATE KEY UPDATE
                name  = VALUES(name),
                ip    = VALUES(ip),
                retry = IF(VALUES(name) IS NULL OR VALUES(ip) IS NULL, retry + 1, 0)
        SQL;

        DB::insert($sql, [
            'domain_id' => (int) $inputs['domain_id'],
            'name' => $inputs['name'],   // null tetap null
            'ip' => $inputs['ip'],
            'retry' => (int) $inputs['retry'],
        ]);
    }
}