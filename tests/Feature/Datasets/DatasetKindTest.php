<?php

use App\Datasets\Datasets;
use App\Enums\DatasetKind;
use App\Timeline\FeedPresets;

it('offers one feed preset per kind holding exactly that kind', function () {
    foreach (DatasetKind::cases() as $kind) {
        $expected = array_keys(array_filter(Datasets::all(), fn ($dataset) => $dataset->kind() === $kind));

        expect(FeedPresets::types($kind->value))->toBe($expected)
            ->and(FeedPresets::all()[$kind->value]['label'])->toBe($kind->label());
    }

    expect(array_slice(array_keys(FeedPresets::all()), 0, 2))->toBe(['curated', 'everything']);
});
