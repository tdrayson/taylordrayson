<?php

namespace App\Console\Commands\Db;

use App\Models\Attachment;
use App\Models\Calorie;
use App\Models\Concerns\Timelineable;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Integrity checks for the invariants nothing else enforces. The timeline spine
 * and its content are joined polymorphically, so no foreign key can guarantee
 * they stay in step, and a break is invisible: the feed filters dangling rows
 * out rather than rendering anything wrong.
 *
 * Exits non-zero when anything is found, so it can run unattended.
 */
#[Signature('db:check {--prune : Delete the dangling rows this finds}')]
#[Description('Check the timeline spine and attachments for broken links')]
class CheckIntegrity extends Command
{
    /** Collections HasAttachments defines the `card` conversion for. */
    private const CARD_COLLECTIONS = ['cover', 'photos', 'artwork'];

    public function handle(): int
    {
        $findings = [
            'Spine rows whose content is gone' => $this->danglingEntries(),
            'Content missing from the spine' => $this->missingEntries(),
            'Food days missing from the spine' => $this->missingFoodDays(),
            'Entry URLs claimed twice on a date' => $this->duplicateSlugs(),
            'Attachments whose owner is gone' => $this->danglingAttachments(),
            'Attachment files missing from disk' => $this->missingOriginals(),
            'Conversions that were never written' => $this->missingConversions(),
        ];

        foreach ($findings as $label => $rows) {
            $this->components->twoColumnDetail(
                $label,
                $rows === [] ? '<fg=green>ok</>' : '<fg=red>'.count($rows).'</>',
            );

            foreach ($rows as $detail) {
                $this->components->bulletList([$detail]);
            }
        }

        $total = array_sum(array_map('count', $findings));

        if ($total === 0) {
            $this->components->info('No integrity problems.');

            return self::SUCCESS;
        }

        if ($this->option('prune')) {
            return $this->prune();
        }

        $this->components->warn('Run again with --prune to delete the dangling rows. The missing ones are fixed by re-saving the model, which lets the observer rebuild the entry.');

        return self::FAILURE;
    }

    /**
     * Spine rows pointing at content that no longer exists. Caused by deleting
     * a row with raw SQL, which skips the model events the observer listens to.
     *
     * @return list<string>
     */
    private function danglingEntries(): array
    {
        return $this->dangling()
            ->groupBy('timelineable_type')
            ->map(fn (Collection $rows, string $type): string => sprintf(
                '%s: %d (ids %s)',
                class_basename($type),
                $rows->count(),
                $rows->pluck('timelineable_id')->take(10)->implode(', '),
            ))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, object{id: int, timelineable_type: string, timelineable_id: int}>
     */
    private function dangling(): Collection
    {
        $entries = DB::table('timeline_entries')
            ->select('id', 'timelineable_type', 'timelineable_id')
            ->get();

        return $entries
            ->groupBy('timelineable_type')
            ->flatMap(function (Collection $rows, string $type): Collection {
                if (! class_exists($type)) {
                    return $rows;
                }

                $live = $type::query()->whereIn('id', $rows->pluck('timelineable_id'))->pluck('id')->all();

                return $rows->reject(fn (object $row): bool => in_array($row->timelineable_id, $live, true));
            });
    }

    /**
     * Content that should be on the timeline but has no spine row, so it is
     * missing from the feed, archives and search. Food is excluded: it gets one
     * entry per day rather than one per row, and is checked separately.
     *
     * @return list<string>
     */
    private function missingEntries(): array
    {
        $findings = [];

        foreach (TypeRegistry::all() as $type) {
            $model = $type['model'];

            if ($model === Calorie::class) {
                continue;
            }

            $missing = $model::query()
                ->doesntHave('timelineEntry')
                ->get()
                ->filter(fn (Model $row): bool => $row instanceof Timelineable && $row->shouldAppearOnTimeline());

            if ($missing->isNotEmpty()) {
                $findings[] = sprintf(
                    '%s: %d (ids %s)',
                    $type['label'],
                    $missing->count(),
                    $missing->take(10)->pluck('id')->implode(', '),
                );
            }
        }

        return $findings;
    }

    /**
     * Days with food logged but no spine row. Food rows are per item, so the
     * observer keeps exactly one entry per day, pointed at that day's first row.
     *
     * @return list<string>
     */
    private function missingFoodDays(): array
    {
        $logged = Calorie::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date')
            ->distinct()
            ->pluck('date');

        $onSpine = TimelineEntry::query()
            ->toBase()
            ->where('timelineable_type', Calorie::class)
            ->selectRaw('DATE(occurred_at) as date')
            ->distinct()
            ->pluck('date');

        $missing = $logged->diff($onSpine);

        return $missing->isEmpty() ? [] : [sprintf('%d day(s): %s', $missing->count(), $missing->take(10)->implode(', '))];
    }

    /**
     * Two entries claiming one URL. Entry URLs are /Y/m/d/slug, so a repeated
     * slug on a date makes one of them unreachable.
     *
     * @return list<string>
     */
    private function duplicateSlugs(): array
    {
        return DB::table('timeline_entries')
            ->selectRaw('DATE(occurred_at) as date, url_slug, COUNT(*) as total')
            ->whereNotNull('url_slug')
            ->groupBy('date', 'url_slug')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn (object $row): string => "{$row->date}/{$row->url_slug} claimed {$row->total} times")
            ->all();
    }

    /**
     * Attachment rows whose stored file is gone.
     *
     * Every other check here reads the database against itself, which cannot
     * see this: the row is intact and the page renders a URL that 404s. Only
     * the disk knows.
     *
     * @return list<string>
     */
    private function missingOriginals(): array
    {
        $missing = $this->eachAttachment(
            fn (Attachment $attachment): bool => ! $this->stored($attachment, $attachment->disk),
        );

        return $this->describe($missing, 'file');
    }

    /**
     * Attachments whose `card` conversion was never written.
     *
     * `media-library:regenerate --only-missing` trusts the `generated_conversions`
     * column, so a conversion the DB believes exists is never rebuilt and the
     * thumbnail stays broken however many times the command is run. Checking
     * the disk is the only way to catch it.
     *
     * @return list<string>
     */
    private function missingConversions(): array
    {
        $missing = $this->eachAttachment(function (Attachment $attachment): bool {
            if (! in_array($attachment->collection_name, self::CARD_COLLECTIONS, true)) {
                return false;
            }

            if (! str_starts_with((string) $attachment->mime_type, 'image/')) {
                return false;
            }

            return ! $this->stored($attachment, $attachment->conversions_disk ?: $attachment->disk, 'card');
        });

        return $this->describe($missing, 'conversion');
    }

    /**
     * Whether an attachment's file (or one of its conversions) is on its disk.
     */
    private function stored(Attachment $attachment, string $disk, string $conversion = ''): bool
    {
        return Storage::disk($disk)->exists($attachment->getPathRelativeToRoot($conversion));
    }

    /**
     * Every attachment the filter keeps, read in chunks so the whole table is
     * never held in memory at once.
     *
     * @param  callable(Attachment): bool  $isMissing
     * @return Collection<int, Attachment>
     */
    private function eachAttachment(callable $isMissing): Collection
    {
        $found = collect();

        Attachment::query()
            ->orderBy('id')
            ->chunk(500, function (Collection $chunk) use ($isMissing, $found): void {
                $found->push(...$chunk->filter($isMissing));
            });

        return $found;
    }

    /**
     * One line per owning type, with a few ids to start from.
     *
     * @param  Collection<int, Attachment>  $missing
     * @return list<string>
     */
    private function describe(Collection $missing, string $noun): array
    {
        return $missing
            ->groupBy('model_type')
            ->map(fn (Collection $group, string $type): string => sprintf(
                '%s: %d %s (attachment ids %s)',
                class_basename($type),
                $group->count(),
                Str::plural($noun, $group->count()),
                $group->take(10)->pluck('id')->implode(', '),
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function danglingAttachments(): array
    {
        $rows = $this->danglingAttachmentRows();

        return $rows->isEmpty() ? [] : $rows
            ->groupBy('model_type')
            ->map(fn (Collection $group, string $type): string => class_basename($type).': '.$group->count())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, object{id: int, model_type: string, model_id: int}>
     */
    private function danglingAttachmentRows(): Collection
    {
        return DB::table('attachments')
            ->select('id', 'model_type', 'model_id')
            ->get()
            ->groupBy('model_type')
            ->flatMap(function (Collection $rows, string $type): Collection {
                if (! class_exists($type)) {
                    return $rows;
                }

                $live = $type::query()->whereIn('id', $rows->pluck('model_id'))->pluck('id')->all();

                return $rows->reject(fn (object $row): bool => in_array($row->model_id, $live, true));
            });
    }

    /**
     * Deletes only what is provably dead: rows pointing at content that is gone.
     * Missing entries are left alone, since rebuilding one means re-saving its
     * model and letting the observer assign a URL slug.
     */
    private function prune(): int
    {
        $entries = $this->dangling();
        $attachments = $this->danglingAttachmentRows();

        if ($entries->isNotEmpty()) {
            TimelineEntry::query()->whereIn('id', $entries->pluck('id'))->delete();
        }

        // One at a time, so each fires the model events that remove the stored
        // file. A bulk query delete would drop the rows and strand the files.
        Attachment::query()
            ->whereIn('id', $attachments->pluck('id'))
            ->get()
            ->each(fn (Attachment $attachment) => $attachment->delete());

        $this->components->info(sprintf(
            'Deleted %d spine row(s) and %d attachment(s).',
            $entries->count(),
            $attachments->count(),
        ));

        return self::SUCCESS;
    }
}
