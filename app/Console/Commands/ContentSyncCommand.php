<?php

namespace App\Console\Commands;

use App\Content\ContentIndex;
use App\Content\EntryFileImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('content:sync {path : Absolute or content-root-relative path to an entry file}')]
#[Description('Re-index a single content file into database/content.sqlite')]
class ContentSyncCommand extends Command
{
    public function handle(ContentIndex $index, EntryFileImporter $importer): int
    {
        $path = $this->argument('path');
        $absolute = File::isFile($path)
            ? $path
            : rtrim((string) config('content.path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);

        if (! File::isFile($absolute)) {
            $this->error('File not found: '.$absolute);

            return self::FAILURE;
        }

        $index->ensureDatabaseFile();

        if (! Schema::connection('content')->hasTable('notes')) {
            $index->migrate();
        }

        $model = $index->using(fn () => $importer->importPath($absolute));

        $this->info('Synced '.$model->flatFileType().' '.$model->getAttribute('ulid').' (#'.$model->getKey().') into content index');

        return self::SUCCESS;
    }
}
