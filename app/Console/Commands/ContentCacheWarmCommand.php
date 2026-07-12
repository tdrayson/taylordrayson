<?php

namespace App\Console\Commands;

use App\Content\ContentIndex;
use App\Content\EntryFileImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:cache:warm')]
#[Description('Rebuild database/content.sqlite from the content tree')]
class ContentCacheWarmCommand extends Command
{
    public function handle(ContentIndex $index, EntryFileImporter $importer): int
    {
        $models = $index->rebuild($importer);

        $this->info('Warmed '.count($models).' entr'.(count($models) === 1 ? 'y' : 'ies').' into '.$index->databasePath());

        return self::SUCCESS;
    }
}
