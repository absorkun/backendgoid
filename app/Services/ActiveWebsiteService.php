<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ActiveWebsiteService
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

        $chunks = array_chunk($domains, self::CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            $responses = $this->getResponses($chunk);

            DB::transaction(function () use ($responses) {
                foreach ($responses as $domain_id => $response) {
                    $this->create([
                        'domain_id' => $domain_id,
                        'status' => $this->getStatus($response),
                        'code' => $this->getStatusCode($response),
                        'retry' => 0,
                    ]);
                }
            });
        }
    }

    private function getDomains(): array
    {
        $sql = <<<SQL
            SELECT d.id, d.name, d.zone
            FROM domains d
            LEFT JOIN websites w ON d.id = w.domain_id AND (w.status = 1 OR w.retry >= 3)
            WHERE d.status = 'active'
                AND (d.zone = '.go.id' OR d.zone = '.desa.id')
                AND w.domain_id IS NULL
            LIMIT ?
        SQL;

        return DB::select($sql, [self::LIMIT_DOMAINS_PER_RUN]);
    }

    private function getResponses(array $chunk): array
    {
        return Http::pool(function (Pool $pool) use ($chunk) {
            foreach ($chunk as $d) {
                $domain = $d->name . $d->zone;
                $options = [
                    'allow_redirects' => true,
                    'verify' => false,
                ];
                $pool->as($d->id)->withOptions($options)
                    ->timeout(self::TIMEOUT_SECONDS_REQUEST)
                    ->connectTimeout(self::TIMEOUT_SECONDS_REQUEST)
                    ->withUserAgent('Mozilla/5.0')
                    ->withHeaders(['Range' => 'bytes=0-512'])
                    ->get("https://$domain");
            }
        });
    }

    private function getStatus($response): bool
    {
        if ($response instanceof Throwable) {
            return false;
        }

        return $response->successful()
            && \strlen(trim(strip_tags($response->body()))) > 9;
    }

    private function getStatusCode(mixed $response): int
    {
        if ($response instanceof Throwable) {
            return 0;
        }

        return $response->status();
    }

    private function create(array $inputs): void
    {
        $sql = <<<SQL
            INSERT INTO websites (domain_id, status, code, retry)
            VALUES (:domain_id, :status, :code, :retry)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                code = VALUES(code),
                retry = IF(VALUES(status) = 0, retry + 1, 0)
        SQL;

        DB::insert($sql, [
            'domain_id' => (int) $inputs['domain_id'],
            'status' => (bool) $inputs['status'] ? 1 : 0,
            'code' => (int) $inputs['code'],
            'retry' => (int) $inputs['retry'],
        ]);
    }
}