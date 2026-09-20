<?php

namespace App\Console\Commands;

use App\Actions\Mentions\SyncMentions;
use App\Models\Concerns\RecordsMentions;
use App\Models\Mention;
use App\Models\Page;
use App\Models\Scopes\ListedScope;
use App\Support\OutboundLinks;
use App\Timeline\TypeRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

#[Signature('mentions:backfill')]
#[Description('Record the links between my own entries on everything written before mentions were tracked')]
class BackfillMentions extends Command
{
    /**
     * Re-derive every source's mentions from the links its body carries now.
     *
     * Each row goes through the same action a save does, so this agrees with
     * the live path by construction and a second run writes nothing.
     */
    public function handle(SyncMentions $sync): int
    {
        foreach ($this->sources() as $class) {
            $type = (new $class)->getMorphClass();
            $recorded = $this->recordedFor($type);
            $scanned = 0;

            $this->linkable($class)->chunkById(200, function (Collection $rows) use ($sync, $recorded, &$scanned): void {
                foreach ($rows as $row) {
                    // A row that links nowhere and holds no mentions has
                    // nothing to write and nothing to clear, so it never
                    // reaches the action and never costs a query.
                    if (OutboundLinks::internalPathsFor($row) === [] && ! isset($recorded[$row->getKey()])) {
                        continue;
                    }

                    $sync($row);
                    $scanned++;
                }
            });

            $this->report($class, $type, $scanned);
        }

        return self::SUCCESS;
    }

    /**
     * Everything that can link to one of my entries: the timeline types, which
     * all carry the trait through HasTimelineEntry, plus pages.
     *
     * @return list<class-string<Model>>
     */
    private function sources(): array
    {
        $classes = array_unique([
            ...array_map(fn (array $type): string => $type['model'], TypeRegistry::all()),
            Page::class,
        ]);

        return array_values(array_filter(
            $classes,
            fn (string $class): bool => in_array(RecordsMentions::class, class_uses_recursive($class), true),
        ));
    }

    /**
     * Rows with something in a column a link could have been written into.
     * Unlisted and private entries are included: both take mentions, and the
     * action decides what each is allowed to record.
     *
     * @param  class-string<Model>  $class
     */
    private function linkable(string $class): Builder
    {
        $table = (new $class)->getTable();

        $columns = array_filter(
            OutboundLinks::SOURCES,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        );

        return $class::query()
            ->withoutGlobalScope(ListedScope::class)
            ->where(function (Builder $query) use ($columns): void {
                foreach ($columns as $column) {
                    $query->orWhereNotNull($column);
                }
            });
    }

    /**
     * The keys of the rows of this type that already record a mention, so a
     * source whose links were removed still gets cleared.
     *
     * @return array<int, true>
     */
    private function recordedFor(string $type): array
    {
        return Mention::query()
            ->where('source_type', $type)
            ->pluck('source_id')
            ->flip()
            ->map(fn (): bool => true)
            ->all();
    }

    private function report(string $class, string $type, int $scanned): void
    {
        if ($scanned === 0) {
            return;
        }

        $mentions = Mention::query()->where('source_type', $type)->count();

        $this->components->info(class_basename($class).": {$scanned} scanned, {$mentions} recorded");
    }
}
