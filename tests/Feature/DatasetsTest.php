<?php

use App\Datasets\Datasets;

it('says how every dataset arrives', function () {
    $synced = [];
    $byHand = [];

    foreach (Datasets::all() as $type => $dataset) {
        $dataset->synced() ? $synced[] = $type : $byHand[] = $type;
    }

    // Book is draftable and synced: the Kindle push creates a draft that is
    // then finished by hand, so draftable() is not a proxy for this.
    expect($synced)->toEqualCanonicalizing([
        'food', 'place', 'activity', 'film', 'tv-episode', 'this-week-with', 'sleep', 'book',
    ])->and($byHand)->toEqualCanonicalizing([
        'note', 'article', 'event', 'fuel', 'flight', 'appearance', 'project',
    ]);
});
