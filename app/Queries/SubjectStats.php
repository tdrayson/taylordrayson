<?php

namespace App\Queries;

use App\Models\Subject;

/**
 * A subject's numbers for its page: entries per type, how many photographs,
 * and the span from first appearance to last. Built over {@see SubjectFeed}
 * and {@see SubjectPhotos} rather than re-resolving the same entries, so the
 * counts can never drift from what the page itself lists.
 */
final class SubjectStats
{
    public function __construct(
        private readonly SubjectFeed $feed,
        private readonly SubjectPhotos $photos,
    ) {}

    /** @return array{entries: array<string, int>, photos: int, first: ?string, last: ?string} */
    public function __invoke(Subject $subject): array
    {
        $groups = ($this->feed)($subject);
        $items = collect($groups)->flatMap(fn (array $group): array => $group['items']);

        return [
            'entries' => $items->countBy(fn (array $item): string => $item['iconKey'])->all(),
            'photos' => count(($this->photos)($subject)),
            'first' => collect($groups)->last()['date'] ?? null,
            'last' => collect($groups)->first()['date'] ?? null,
        ];
    }
}
