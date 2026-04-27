<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class EmailToExpiredDomainService
{
    protected string $gmailUser;
    protected string $gmailPass;

    public function __construct()
    {
        $this->gmailUser = env('GMAIL_USER');
        $this->gmailPass = env('GMAIL_PASS');
    }

    public function send()
    {
        $transport = Transport::fromDsn(
            "smtp://{$this->gmailUser}:{$this->gmailPass}@smtp.gmail.com:587"
        );
        $mailer = new Mailer($transport);

        foreach ($this->data() as $row) {
            $email = $this->email($row);
            $mailer->send($email);
        }
    }

    private function data()
    {
        $sql = <<<SQL
            SELECT d.name as domainName, d.zone as domainZone, d.expired_date as domainExpired, u.email as userEmail
            FROM domains d
            LEFT JOIN users u ON u.id = d.user_id
            WHERE d.status = 'active'
        SQL;

        return DB::select($sql);
    }

    private function email($record, ?string $subject = 'Domain Kadaluarsa')
    {
        return (new Email())
            ->from()
            ->to($record->userEmail)
            ->subject($subject)
            ->html($this->html($record->domainName . $record->domainZone, $record->userEmail));
    }

    private function html(string $domain, string $expired_date)
    {
        return <<<HTML
                <html>
                    <body style="background-color: smokewhite;">
                        <h2 style="text-align: right;">Notifikasi Domain Kadaluarsa</h2>
                        <table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f2f2f2;">
                                    <th>Domain</th>
                                    <th>Tanggal Kadaluarsa</th>
                                </tr>
                            </thead>
                            <thead>
                                <tr>
                                    <td>{$domain}</td>
                                    <td>{$expired_date}</td>
                                </tr>
                            </thead>
                        </table>
                    </body>
                </html>
                HTML;
    }
}