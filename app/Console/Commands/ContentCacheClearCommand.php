<?php

namespace App\Console\Commands;

use App\Content\ContentIndex;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:cache:clear')]
#[Description('Delete the rebuildable content SQLite index file')]
class ContentCacheClearCommand extends Command
{
    public function handle(ContentIndex $index): int
    {
        $path = $index->databasePath();
        $index->clear();

        if ($path !== '' && $path !== ':memory:') {
            $this->info('Deleted '.$path.' (recreated on next content:cache:warm)');
        } else {
            $this->info('No content SQLite index file to clear.');
        }

        return self::SUCCESS;
    }
}
