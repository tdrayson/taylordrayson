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

    /** @return array{entries: array<string, int>, photos: int, first: ?string, last: ?string, span: ?string} */
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
            'span' => $this->span($first, $last),
        ];
    }

    /**
     * The first-to-last span as a sentence, at month precision: the exact day
     * of a years-long span is noise, and the page already lists the entries.
     */
    private function span(?string $first, ?string $last): ?string
    {
        if ($first === null || $last === null) {
            return null;
        }

        $from = Carbon::parse($first)->format('F Y');
        $to = Carbon::parse($last)->format('F Y');

        return $from === $to ? $from : "{$from} to {$to}";
    }
}
