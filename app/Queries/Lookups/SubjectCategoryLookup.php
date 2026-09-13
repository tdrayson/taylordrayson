<?php

namespace App\Queries\Lookups;

use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use App\Models\Subject;

/**
 * Categories to offer while filing a subject: the ones already in use, plus
 * the enum's own suggestions. The set is open, so this is a starting point
 * rather than the permitted values.
 */
final class SubjectCategoryLookup
{
    /**
     * @return list<array{value: string, label: string, detail: string}>
     */
    public function __invoke(string $query, ?SubjectKind $kind = null): array
    {
        $used = Subject::query()
            ->when($kind !== null, fn ($builder) => $builder->where('kind', $kind))
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        $suggested = collect($kind === null ? SubjectCategory::cases() : SubjectCategory::forKind($kind))
            ->map(fn (SubjectCategory $category): string => $category->value);

        return $used->merge($suggested)
            ->unique()
            ->filter(fn (string $value): bool => $query === '' || str_contains($value, SubjectCategory::normalise($query) ?? ''))
            ->sort()
            ->take(10)
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => SubjectCategory::labelFor($value),
                'detail' => '',
            ])
            ->values()
            ->all();
    }
}
