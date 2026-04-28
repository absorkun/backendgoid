<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DomainCsvService
{
    private const array HEADERS_CSV = [
        'No',
        'Domain',
        'Status',
        'Kadaluarsa',
        'Web Status',
        'Web Code',
        'Nameserver',
        'A Record',
    ];

    public function handle()
    {
        $path = storage_path('app/public/csv/');
        $fileName = $path . 'domain-' . now()->format('YmdHis') . '.csv';
        $file = fopen($fileName, 'w');
        fputcsv($file, self::HEADERS_CSV, escape: "");

        foreach ($this->getDomains() as $i => $d) {
            fputcsv(
                $file,
                [
                    $i + 1,
                    $d->domainName,
                    $d->domainStatus,
                    $d->domainExpired,
                    $d->websiteStatus,
                    $d->websiteCode,
                    $d->nameserverName,
                    $d->nameserverIp,
                ],
                escape: ""
            );
        }

        fclose($file);
    }

    private function getDomains()
    {
        $sql = <<<SQL
            SELECT 
                CONCAT(d.name,d.zone) AS domainName, d.status AS domainStatus, d.expired_date AS domainExpired,
                CASE WHEN w.status = 0 THEN 'inactive' ELSE 'active' END AS websiteStatus, w.code AS websiteCode,
                n.name AS nameserverName, n.ip AS nameserverIp
            FROM domains d
            LEFT JOIN websites w
                ON d.id = w.domain_id
            LEFT JOIN nameservers n
                ON d.id = n.domain_id
            WHERE d.status = 'active'
                AND (d.zone = '.go.id' OR d.zone = '.desa.id')
            ORDER BY d.id
        SQL;

        return DB::select($sql);
    }
}