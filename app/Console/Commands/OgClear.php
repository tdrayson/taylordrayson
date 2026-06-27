<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('og:clear')]
#[Description('Purge all cached Open Graph cards (run after bumping OG_VERSION).')]
class OgClear extends Command
{
    /**
     * Purge every cached Open Graph card image from local storage.
     *
     * @return int The command exit code.
     */
    public function handle(): int
    {
        Storage::disk('local')->deleteDirectory('og');

        $this->info('Cleared all cached Open Graph cards.');

        return self::SUCCESS;
    }
}
