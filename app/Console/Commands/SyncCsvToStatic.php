<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('sync:csv-static')]
#[Description('Command description')]
class SyncCsvToStatic extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sourceDir = storage_path('app/public/csv');
        $targetDir = '/var/www/static/csv';

        if (!File::exists($sourceDir)) {
            return;
        }

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        foreach (File::files($sourceDir) as $file) {
            $targetPath = $targetDir . '/' . $file->getFilename();

            // copy only if new or modified
            if (
                !File::exists($targetPath) ||
                File::lastModified($file->getPathname()) > File::lastModified($targetPath)
            ) {
                File::copy($file->getPathname(), $targetPath);
            }
        }
    }
}
