<?php

namespace App\Console\Commands;

use App\Services\ActiveWebsiteService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('website:check-active')]
#[Description('Command description')]
class CheckActiveWebsites extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai mengecek website....');

        try {
            $start = microtime(true);
            (new ActiveWebsiteService())->handle();

            $duration = round(microtime(true) - $start, 2);
            $this->info("Pengecekan selesai dalam $duration detik!");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
