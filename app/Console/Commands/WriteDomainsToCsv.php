<?php

namespace App\Console\Commands;

use App\Services\DomainCsvService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domain:write-csv')]
#[Description('Command description')]
class WriteDomainsToCsv extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai mencetak csv....');

        try {
            $start = microtime(true);
            (new DomainCsvService())->handle();

            $duration = round(microtime(true) - $start, 2);
            $this->info("Pencetakan selesai dalam $duration detik!");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
