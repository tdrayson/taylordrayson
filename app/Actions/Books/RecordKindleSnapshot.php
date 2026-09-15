<?php

namespace App\Actions\Books;

use App\Data\KindleItem;
use App\Data\KindleSyncResult;
use App\Enums\EntryStatus;
use App\Enums\Source;
use App\Models\Book;
use App\Support\BookCompleteness;
use App\Support\BookProgress;
use App\Support\EntryInstant;
use App\Support\EntryZone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Upsert every book in a Kindle snapshot as a draft, publishing a complete one
 * at 100%. A snapshot is absolute state, so books absent from it are left alone.
 */
final class RecordKindleSnapshot
{
    private const FUTURE_TOLERANCE_HOURS = 24;

    public function __construct(private readonly EntryZone $zones) {}

    /**
     * @param  list<KindleItem>  $items
     */
    public function __invoke(array $items): KindleSyncResult
    {
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'published' => 0];

        DB::transaction(function () use ($items, &$counts): void {
            foreach ($items as $item) {
                $counts[$this->record($item)]++;
            }
        });

        return new KindleSyncResult(...$counts);
    }

    /**
     * @return 'created'|'updated'|'unchanged'|'skipped'|'published'
     */
    private function record(KindleItem $item): string
    {
        if (! $item->isBook() || $item->lastOpenedAt->isAfter(now()->addHours(self::FUTURE_TOLERANCE_HOURS))) {
            return 'skipped';
        }

        $book = Book::query()
            ->where('source', Source::Kindle->value)
            ->where('source_id', $item->cdeKey)
            ->first();

        if ($book === null) {
            $this->create($item);

            return 'created';
        }

        $storedAt = EntryInstant::utc($book->progressed_at, $book->timezone);

        if ($book->status !== EntryStatus::Draft
            || $book->progress_percent === $item->percent
            || ($storedAt !== null && $item->lastOpenedAt->isBefore($storedAt))) {
            return 'unchanged';
        }

        $local = $this->local($item->lastOpenedAt, $book->timezone);
        $book->fill(['progress_percent' => $item->percent, 'progressed_at' => $local])->save();

        if ($item->percent >= BookProgress::FINISHED && BookCompleteness::forBook($book) === []) {
            $book->fill(['status' => EntryStatus::Published, 'occurred_at' => $local])->save();

            return 'published';
        }

        return 'updated';
    }

    private function create(KindleItem $item): void
    {
        $zone = EntryInstant::zone($this->zones->forEntryAt($item->lastOpenedAt));
        $local = $this->local($item->lastOpenedAt, $zone);

        Book::create([
            'title' => $item->title,
            'source' => Source::Kindle->value,
            'source_id' => $item->cdeKey,
            'status' => EntryStatus::Draft,
            'timezone' => $zone,
            'started_at' => $local,
            'progress_percent' => $item->percent,
            'progressed_at' => $local,
            'meta' => [],
        ]);
    }

    /** The wall-clock reading in the book's zone, which is how every entry date is stored. */
    private function local(CarbonImmutable $instant, ?string $timezone): string
    {
        return $instant->setTimezone(EntryInstant::zone($timezone))->format('Y-m-d H:i:s');
    }
}
