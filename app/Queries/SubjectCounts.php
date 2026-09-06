<?php

namespace App\Queries;

use App\Enums\SubjectKind;
use App\Models\Subject;

/**
 * How many subjects exist per kind, keyed by URL segment for the /life hub
 * and the /more row. Every kind is present even at zero.
 */
final class SubjectCounts
{
    /** @return array<string, int> */
    public function __invoke(): array
    {
        $counts = Subject::query()
            ->selectRaw('kind, count(*) as total')
            ->groupBy('kind')
            ->get()
            ->mapWithKeys(fn (Subject $row): array => [$row->kind->segment() => (int) $row->total]);

        return collect(SubjectKind::cases())
            ->mapWithKeys(fn (SubjectKind $kind): array => [$kind->segment() => $counts->get($kind->segment(), 0)])
            ->all();
    }
}
