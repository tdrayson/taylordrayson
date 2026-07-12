<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('media:relocate-ulid {--dry-run : Show moves without writing}')]
#[Description('Move Media Library files from {id}/ into media/{entry-ulid}/')]
class MediaRelocateToUlidCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        Attachment::query()->orderBy('id')->each(function (Attachment $attachment) use ($dryRun, &$moved, &$skipped): void {
            $model = $attachment->model;

            if ($model === null) {
                $skipped++;

                return;
            }

            if (blank($model->getAttribute('ulid'))) {
                if ($dryRun) {
                    $this->line("Would assign ulid to {$attachment->model_type}#{$attachment->model_id}");
                } else {
                    $model->setAttribute('ulid', (string) Str::ulid());
                    $model->saveQuietly();
                }
            }

            $ulid = (string) ($model->getAttribute('ulid') ?? '');
            $disk = Storage::disk($attachment->disk);
            $oldBase = (string) $attachment->getKey();
            $newBase = 'media/'.$ulid;

            if ($oldBase === $newBase || ! $disk->exists($oldBase)) {
                $skipped++;

                return;
            }

            $files = $disk->allFiles($oldBase);

            if ($files === []) {
                $skipped++;

                return;
            }

            foreach ($files as $file) {
                $relative = Str::after($file, $oldBase.'/');
                $destination = $newBase.'/'.$relative;

                if ($dryRun) {
                    $this->line("{$file} → {$destination}");

                    continue;
                }

                if ($disk->exists($destination)) {
                    $disk->delete($destination);
                }

                $disk->move($file, $destination);
            }

            if (! $dryRun) {
                $disk->deleteDirectory($oldBase);
            }

            $moved++;
        });

        $this->info(($dryRun ? 'Would relocate' : 'Relocated')." {$moved} attachment folder(s); skipped {$skipped}");

        return self::SUCCESS;
    }
}
