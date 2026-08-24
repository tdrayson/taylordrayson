<?php

namespace App\Console\Commands;

use App\Support\OgRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('og:clear {--stale : Keep the cards for the current design and delete every superseded one}')]
#[Description('Purge cached Open Graph cards')]
class OgClear extends Command
{
    /**
     * Delete cached Open Graph cards, either all of them or only the
     * generations no longer being served.
     *
     * Cards are rendered on demand, so deleting one costs a re-render on next
     * request and nothing else. Superseded generations cost only disk: nothing
     * points at them and nothing ever will, since the path they live under is
     * derived from a design that has already changed.
     */
    public function handle(): int
    {
        $disk = Storage::disk('local');

        if (! $this->option('stale')) {
            $disk->deleteDirectory('og');

            $this->components->info('Cleared every cached Open Graph card.');

            return self::SUCCESS;
        }

        $current = OgRenderer::generation();

        $stale = collect($disk->directories('og'))
            ->reject(fn (string $directory): bool => basename($directory) === $current);

        foreach ($stale as $directory) {
            $disk->deleteDirectory($directory);
        }

        // Cards used to be written straight into og/, before they were filed by
        // generation. Nothing writes there now, so anything loose is from the
        // old layout and unreachable.
        $loose = $disk->files('og');

        $disk->delete($loose);

        if ($stale->isEmpty() && $loose === []) {
            $this->components->info("Nothing stale: {$current} is the only generation on disk.");

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Deleted %d superseded %s and %d loose %s, keeping %s.',
            $stale->count(),
            str('generation')->plural($stale->count()),
            count($loose),
            str('card')->plural(count($loose)),
            $current,
        ));

        return self::SUCCESS;
    }
}
