<?php

namespace App\Console\Commands;

use App\Services\RecordNameserverService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nameserver:check-record')]
#[Description('Command description')]
class CheckRecordNameservers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai mengecek nameserver....');

        try {
            $start = microtime(true);
            (new RecordNameserverService())->handle();

            $duration = round(microtime(true) - $start, 2);
            $this->info("Pengecekan selesai dalam $duration detik!");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
