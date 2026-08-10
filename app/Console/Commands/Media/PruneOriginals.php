<?php

namespace App\Console\Commands\Media;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the imported original of every image that has an optimised copy, making
 * the 1920px WebP the stored master. Irreversible: conversions regenerate FROM the
 * original, so `card` and the responsive set can never be rebuilt afterwards.
 * Dry run unless --force is given.
 */
#[Signature('media:prune-originals
    {--collection=* : Limit to these collections}
    {--force : Actually delete; without this the command only reports}')]
#[Description('Delete imported originals that have an optimised copy, keeping the copy as the master')]
class PruneOriginals extends Command
{
    /**
     * Collections whose originals are never pruned.
     *
     * `audio` holds the mirrored podcast episodes: they have no conversion at
     * all, so the original is the only copy there is.
     *
     * @var array<int, string>
     */
    private const PROTECTED_COLLECTIONS = ['audio'];

    public function handle(): int
    {
        $prunable = $this->prunableImages();

        if ($prunable->isEmpty()) {
            $this->components->info('Nothing to prune.');

            return self::SUCCESS;
        }

        $bytes = $prunable->sum('size');
        $force = (bool) $this->option('force');

        $this->components->info($prunable->count().' original(s), '.$this->megabytes($bytes).', have an optimised copy.');

        foreach ($prunable->groupBy('collection_name') as $collection => $images) {
            $this->components->twoColumnDetail((string) $collection, $images->count().' file(s), '.$this->megabytes($images->sum('size')));
        }

        if (! $force) {
            $this->newLine();
            $this->components->warn('Dry run. Nothing deleted. Re-run with --force to delete.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $freed = 0;
        $bar = $this->output->createProgressBar($prunable->count());
        $bar->start();

        foreach ($prunable as $image) {
            if ($this->deleteOriginal($image)) {
                $deleted++;
                $freed += $image->size;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Deleted {$deleted} original(s), freeing ".$this->megabytes($freed).'.');

        return self::SUCCESS;
    }

    /**
     * Remove the imported file, leaving the conversions and the database row.
     *
     * The row stays because the conversions hang off it; it is marked so the
     * size column, which still holds the original's bytes, is not later read
     * as though that file were still on disk.
     */
    private function deleteOriginal(Attachment $image): bool
    {
        $disk = Storage::disk($image->disk);
        $path = $image->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            return false;
        }

        $disk->delete($path);

        $image->setCustomProperty('original_pruned', true);
        $image->save();

        return true;
    }

    /**
     * Images whose optimised copy exists as a real file.
     *
     * Both halves matter. A row can claim a generated conversion whose file has
     * since gone, and deleting the original on that claim would leave nothing
     * to serve at all. Anything without a `full` copy is skipped, which is what
     * keeps the SVGs and the one GIF, deliberately never converted, intact.
     *
     * @return Collection<int, Attachment>
     */
    private function prunableImages(): Collection
    {
        return $this->imageQuery()->get()->filter(function (Attachment $image): bool {
            if (! $image->hasGeneratedConversion('full') || $image->getCustomProperty('original_pruned')) {
                return false;
            }

            $conversion = $image->getPath('full');

            return is_file($conversion) && filesize($conversion) > 0;
        })->values();
    }

    private function imageQuery(): Builder
    {
        return Attachment::query()
            ->where('mime_type', 'like', 'image/%')
            ->whereNotIn('collection_name', self::PROTECTED_COLLECTIONS)
            ->when($this->option('collection'), fn ($query, array $collections) => $query->whereIn('collection_name', $collections))
            ->orderBy('id');
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
