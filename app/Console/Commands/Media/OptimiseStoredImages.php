<?php

namespace App\Console\Commands\Media;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Throwable;

/**
 * Renders the optimised `full` copy of every stored image. Writes only: originals
 * stay put so the output can be checked before {@see PruneOriginals} removes them.
 * Re-runnable, and skips images that already have their copy.
 */
#[Signature('media:optimise
    {--collection=* : Limit to these collections}
    {--limit= : Stop after this many images}
    {--now : Render in the foreground instead of queueing}
    {--report : Only report what is already rendered, converting nothing}')]
#[Description('Render the optimised full-size copy of every stored image, leaving originals untouched')]
class OptimiseStoredImages extends Command
{
    /** Media types deliberately left as they are; see HasAttachments. */
    private const SKIPPED_TYPES = ['image/svg+xml', 'image/gif'];

    public function handle(FileManipulator $manipulator): int
    {
        $images = $this->targetImages();

        if ($images->isEmpty()) {
            $this->components->info('Nothing to convert.');

            return self::SUCCESS;
        }

        if ($this->option('report')) {
            $this->report();

            return self::SUCCESS;
        }

        $this->convert($manipulator, $images);

        // Only when the work actually happened. Queued, the conversions have
        // not run yet, and printing the table here reports the state from
        // before the command was invoked: it reads as a finished run that
        // converted almost nothing.
        if ($this->option('now')) {
            $this->report();

            return self::SUCCESS;
        }

        $this->components->info('Queued '.$images->count().' conversion(s). None have run yet.');
        $this->components->warn('Start a worker in THIS project, then check with --report:');
        $this->line('    php artisan queue:work --stop-when-empty');
        $this->newLine();
        $this->line('    php artisan media:optimise --report');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Attachment>  $images
     */
    private function convert(FileManipulator $manipulator, Collection $images): void
    {
        // This flag rather than queue.default: each Conversion reads it as it is
        // built, whereas switching the connection leaves the already-resolved
        // manager pointing at the database queue and the run reports nothing.
        if ($this->option('now')) {
            config(['media-library.queue_conversions_by_default' => false]);
        }

        $this->components->info($images->count().' image(s) to convert.');

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();
        $failed = 0;

        foreach ($images as $image) {
            try {
                $manipulator->createDerivedFiles($image, ['full'], onlyMissing: true);
            } catch (Throwable $exception) {
                // One unreadable file should not end a run over 8,000 of them.
                $failed++;
                $this->newLine();
                $this->components->warn("#{$image->id} {$image->file_name}: {$exception->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($failed > 0) {
            $this->components->warn("{$failed} image(s) failed to convert.");
        }
    }

    /**
     * Original bytes against optimised bytes, per collection.
     *
     * The point of the command: the saving has to be visible, and the output
     * has to be there to look at, before the originals are considered for
     * deletion.
     */
    private function report(): void
    {
        $rows = [];
        $originalTotal = 0;
        $optimisedTotal = 0;
        $doneTotal = 0;
        $imageTotal = 0;

        foreach ($this->imageQuery()->get()->groupBy('collection_name') as $collection => $images) {
            // Only the images that have a copy, on both sides of the comparison.
            // Weighing every original against the handful converted so far
            // reads as a 100% saving, which is the one number that must not be
            // wrong here: it is what the decision to delete rests on.
            $converted = $images->filter(fn (Attachment $image): bool => $this->optimisedSize($image) > 0);

            $imageTotal += $images->count();
            $doneTotal += $converted->count();

            if ($converted->isEmpty()) {
                $rows[] = [$collection, '0/'.$images->count(), '-', '-', '-'];

                continue;
            }

            $original = $converted->sum('size');
            $optimised = $converted->sum(fn (Attachment $image): int => $this->optimisedSize($image));
            $originalTotal += $original;
            $optimisedTotal += $optimised;

            $rows[] = [
                $collection,
                $converted->count().'/'.$images->count(),
                $this->megabytes($original),
                $this->megabytes($optimised),
                $this->saving($original, $optimised),
            ];
        }

        if ($doneTotal === 0) {
            $this->components->warn('No optimised copies rendered yet. Run a queue worker, or pass --now.');

            return;
        }

        $rows[] = [
            '<options=bold>total</>',
            $doneTotal.'/'.$imageTotal,
            $this->megabytes($originalTotal),
            $this->megabytes($optimisedTotal),
            $this->saving($originalTotal, $optimisedTotal),
        ];

        $this->table(['collection', 'converted', 'original', 'optimised', 'saved'], $rows);
        $this->components->info('Originals left in place. Nothing has been deleted.');
    }

    private function optimisedSize(Attachment $image): int
    {
        if (! $image->hasGeneratedConversion('full')) {
            return 0;
        }

        $path = $image->getPath('full');

        return is_file($path) ? filesize($path) : 0;
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }

    private function saving(int $original, int $optimised): string
    {
        return $original === 0 ? '-' : round(100 - ($optimised / $original * 100)).'%';
    }

    /**
     * @return Collection<int, Attachment>
     */
    private function targetImages(): Collection
    {
        $query = $this->imageQuery();

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        return $query->get();
    }

    /**
     * Every stored image eligible for conversion, oldest first so an
     * interrupted run resumes where it stopped.
     */
    private function imageQuery(): Builder
    {
        return Attachment::query()
            ->where('mime_type', 'like', 'image/%')
            ->whereNotIn('mime_type', self::SKIPPED_TYPES)
            ->when($this->option('collection'), fn ($query, array $collections) => $query->whereIn('collection_name', $collections))
            ->orderBy('id');
    }
}
