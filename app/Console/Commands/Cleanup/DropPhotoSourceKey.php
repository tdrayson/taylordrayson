<?php

namespace App\Console\Commands\Cleanup;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Drops the `source` custom property events:import-photos stamped on the
 * event photographs it attached, now that the import has run and retired.
 * One-off: deleted itself in the same PR that deletes this command.
 */
#[Signature('photos:drop-source-key')]
#[Description('Drop the retired source custom property from event photographs')]
class DropPhotoSourceKey extends Command
{
    public function handle(): int
    {
        $rows = Attachment::query()->whereNotNull('custom_properties->source')->get();

        foreach ($rows as $row) {
            $row->forgetCustomProperty('source')->save();
        }

        $this->components->info("Dropped 'source' from {$rows->count()} photograph(s).");

        return self::SUCCESS;
    }
}
