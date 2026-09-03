<?php

namespace App\Queries;

use App\Models\Activity;
use App\Models\Subject;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A subject's numbers for its page: entries per type, photo count, and its
 * first-to-last span, built over {@see SubjectFeed} and {@see SubjectPhotos}.
 */
final class SubjectStats
{
    public function __construct(
        private readonly SubjectFeed $feed,
        private readonly SubjectPhotos $photos,
    ) {}

    /** @return array{entries: array<string, int>, photos: int, first: ?string, last: ?string, firstLabel: ?string, lastLabel: ?string, rows: list<array<string, mixed>>} */
    public function __invoke(Subject $subject): array
    {
        $groups = ($this->feed)($subject);
        $items = collect($groups)->flatMap(fn (array $group): array => $group['items']);

        $first = collect($groups)->last()['date'] ?? null;
        $last = collect($groups)->first()['date'] ?? null;
        $byType = $items->countBy(fn (array $item): string => $item['iconKey']);
        $photos = count(($this->photos)($subject));

        // Month precision: the exact day of a years-long span is noise, and
        // the page lists every entry anyway.
        $firstLabel = $first === null ? null : Carbon::parse($first)->format('F Y');
        $lastLabel = $last === null ? null : Carbon::parse($last)->format('F Y');

        return [
            'entries' => $byType->all(),
            'photos' => $photos,
            'first' => $first,
            'last' => $last,
            'firstLabel' => $firstLabel,
            'lastLabel' => $lastLabel,
            'rows' => $this->rows($subject, $byType, $photos, $firstLabel, $lastLabel),
        ];
    }

    /**
     * The numbers as label/value rows, to sit under a subject's typed-in facts
     * rather than as a sentence beside them.
     *
     * @param  Collection<string, int>  $byType
     * @return list<array<string, mixed>>
     */
    private function rows(Subject $subject, Collection $byType, int $photos, ?string $firstLabel, ?string $lastLabel): array
    {
        $types = TypeRegistry::all();

        $rows = $byType
            ->sortDesc()
            ->map(fn (int $total, string $key): array => [
                'label' => $types[$key]['label'] ?? ucfirst($key),
                'value' => number_format($total),
            ])
            ->values()
            ->all();

        if ($photos > 0) {
            $rows[] = ['label' => 'Photos', 'value' => number_format($photos)];
        }

        $distance = $this->distance($subject);

        if ($distance > 0) {
            // Raw metres: the row formats through the visitor's mi/km setting.
            $rows[] = ['label' => 'Distance', 'distanceM' => $distance];
        }

        if ($firstLabel !== null) {
            $rows[] = ['label' => 'First', 'value' => $firstLabel];

            if ($lastLabel !== $firstLabel) {
                $rows[] = ['label' => 'Latest', 'value' => $lastLabel];
            }
        }

        return $rows;
    }

    /** Metres across every activity the subject is on, direct or through a photograph. */
    private function distance(Subject $subject): int
    {
        $ids = $this->feed->targets($subject)
            ->where('type', Activity::class)
            ->pluck('id');

        return $ids->isEmpty() ? 0 : (int) Activity::query()->whereIn('id', $ids)->sum('distance');
    }
}
