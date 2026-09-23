<?php

namespace App\Console\Commands;

use App\Models\TimelineEntry;
use App\Support\OgRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('og:clear {--stale : Keep the current cards and delete superseded designs, old-layout cards and deleted entries\' cards}')]
#[Description('Purge cached Open Graph cards')]
class OgClear extends Command
{
    /**
     * Delete cached Open Graph cards, either all of them or only the
     * ones nothing can reach any more.
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

        $orphans = $this->orphans($current);

        $disk->delete($orphans);

        if ($stale->isEmpty() && $loose === [] && $orphans === []) {
            $this->components->info("Nothing stale: {$current} is the only generation on disk.");

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Deleted %d superseded %s and %d orphaned %s, keeping %s.',
            $stale->count(),
            str('generation')->plural($stale->count()),
            count($loose) + count($orphans),
            str('card')->plural(count($loose) + count($orphans)),
            $current,
        ));

        return self::SUCCESS;
    }

    /**
     * Current-design cards nothing can reach: those from before cards were
     * named by owner, and those whose entry has since been deleted.
     *
     * @return list<string>
     */
    private function orphans(string $current): array
    {
        $disk = Storage::disk('local');
        $unowned = $disk->files("og/{$current}");
        $entryCards = [];

        foreach ($disk->files("og/{$current}/entry") as $file) {
            if (preg_match('/^(\d+)-\d+\.png$/', basename($file), $match)) {
                $entryCards[(int) $match[1]][] = $file;
            } else {
                $unowned[] = $file;
            }
        }

        $live = collect(array_keys($entryCards))
            ->chunk(500)
            ->flatMap(fn (Collection $ids): Collection => TimelineEntry::withoutGlobalScopes()->whereKey($ids->all())->pluck('id'))
            ->all();

        return [...$unowned, ...collect($entryCards)->except($live)->flatten()->all()];
    }
}
