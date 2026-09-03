<?php

namespace App\Queries;

use App\Models\Subject;
use Illuminate\Support\Carbon;

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

    /** @return array{entries: array<string, int>, photos: int, first: ?string, last: ?string, firstLabel: ?string, lastLabel: ?string} */
    public function __invoke(Subject $subject): array
    {
        $groups = ($this->feed)($subject);
        $items = collect($groups)->flatMap(fn (array $group): array => $group['items']);

        $first = collect($groups)->last()['date'] ?? null;
        $last = collect($groups)->first()['date'] ?? null;

        return [
            'entries' => $items->countBy(fn (array $item): string => $item['iconKey'])->all(),
            'photos' => count(($this->photos)($subject)),
            'first' => $first,
            'last' => $last,
            // Month precision: the exact day of a years-long span is noise,
            // and the page lists every entry anyway.
            'firstLabel' => $first === null ? null : Carbon::parse($first)->format('F Y'),
            'lastLabel' => $last === null ? null : Carbon::parse($last)->format('F Y'),
        ];
    }
}
