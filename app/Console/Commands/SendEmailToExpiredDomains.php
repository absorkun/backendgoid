<?php

namespace App\Console\Commands;

use App\Services\EmailToExpiredDomainService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domain:send-email-to-expired')]
#[Description('Command description')]
class SendEmailToExpiredDomains extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Started');
        $start = microtime(true);
        try {
            (new EmailToExpiredDomainService())->send();
            $time = round(microtime(true) - $start, 2);
            $this->info("Finished in {$time}s");
            return Self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Self::FAILURE;
        }
    }
}
