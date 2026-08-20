<?php

namespace App\Console\Commands\Media;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Moves each image's `full` conversion into the slot its original occupies, so
 * the optimised 1920px WebP becomes the original rather than a variation of one.
 *
 * A conversion is derived data: regenerating or clearing conversions is expected
 * to be safe, and it is not while the only surviving copy lives there. Promoting
 * restores that guarantee, and leaves old media the same shape as anything
 * ingested now, which arrives as WebP already.
 *
 * Idempotent, so a partial run can simply be repeated. Dry run unless --force.
 */
#[Signature('media:promote-full
    {--collection=* : Limit to these collections}
    {--force : Actually promote; without this the command only reports}')]
#[Description('Make the optimised WebP the stored original instead of a conversion of it')]
class PromoteFullToOriginal extends Command
{
    public function handle(): int
    {
        $promotable = $this->promotable();

        if ($promotable->isEmpty()) {
            $this->components->info('Nothing to promote.');

            return self::SUCCESS;
        }

        $this->components->info($promotable->count().' image(s) have a full conversion to promote.');

        foreach ($promotable->groupBy('collection_name') as $collection => $images) {
            $this->components->twoColumnDetail(
                (string) $collection,
                $images->count().' file(s), '.$this->megabytes($images->sum(fn (Attachment $m): int => (int) @filesize($m->getPath('full'))))
            );
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->components->warn('Dry run. Nothing moved. Re-run with --force to promote.');

            return self::SUCCESS;
        }

        $promoted = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($promotable->count());
        $bar->start();

        foreach ($promotable as $image) {
            try {
                $this->promote($image);
                $promoted++;
            } catch (Throwable $exception) {
                $failed++;
                $this->newLine();
                $this->components->error("Media {$image->id}: {$exception->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Promoted {$promoted} image(s).");

        if ($failed > 0) {
            $this->components->warn("{$failed} failed and were left untouched. Re-run to retry them.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Move the conversion into the original's place and repoint the row at it.
     *
     * The old original is removed only once the move has succeeded, so an
     * interrupted run leaves a row that is still servable and still promotable.
     */
    private function promote(Attachment $image): void
    {
        $conversion = $image->getPath('full');
        $previous = $image->getPath();
        $target = dirname($previous).'/'.pathinfo($image->file_name, PATHINFO_FILENAME).'.webp';

        if (! is_file($conversion) || filesize($conversion) === 0) {
            throw new \RuntimeException('full conversion is missing or empty');
        }

        if (! @rename($conversion, $target)) {
            throw new \RuntimeException('could not move the conversion into place');
        }

        if ($previous !== $target && is_file($previous)) {
            @unlink($previous);
        }

        $conversions = $image->generated_conversions ?? [];
        unset($conversions['full']);

        $image->file_name = basename($target);
        $image->mime_type = 'image/webp';
        $image->size = filesize($target);
        $image->generated_conversions = $conversions;
        $image->forgetCustomProperty('original_pruned');
        $image->save();
    }

    /**
     * Images holding a real `full` file. Anything already promoted has no such
     * conversion left, which is what makes a repeated run a no-op.
     *
     * @return Collection<int, Attachment>
     */
    private function promotable(): Collection
    {
        return Attachment::query()
            ->where('mime_type', 'like', 'image/%')
            ->when($this->option('collection'), fn ($query, array $collections) => $query->whereIn('collection_name', $collections))
            ->orderBy('id')
            ->get()
            ->filter(fn (Attachment $image): bool => $image->hasGeneratedConversion('full')
                && is_file($image->getPath('full'))
                && filesize($image->getPath('full')) > 0)
            ->values();
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
