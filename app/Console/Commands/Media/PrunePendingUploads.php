<?php

namespace App\Console\Commands\Media;

use App\Support\PendingUploads;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Sweeps uploads parked by the entry editor and never attached to anything.
 *
 * {@see PendingUploads::store()} prunes as a side effect of the next upload, so
 * without this a form abandoned today is only cleaned when someone happens to
 * upload again, however long that takes.
 */
#[Signature('media:prune-pending')]
#[Description('Delete entry-editor uploads that were never attached to an entry')]
class PrunePendingUploads extends Command
{
    public function handle(): int
    {
        $directory = PendingUploads::directory();

        if (! File::isDirectory($directory)) {
            $this->components->info('Nothing parked.');

            return self::SUCCESS;
        }

        $before = count(File::directories($directory));

        PendingUploads::prune();

        $removed = $before - count(File::directories($directory));

        $this->components->info("Removed {$removed} abandoned upload(s).");

        return self::SUCCESS;
    }
}
