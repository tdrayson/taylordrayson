<?php

use App\Content\ContentTypes;
use App\Contracts\DefinesContentSchema;
use App\Models\Fuel;
use App\Models\FuelStation;
use App\Models\Page;
use App\Models\TimelineEntry;

it('maps every file type to a model class', function () {
    expect(ContentTypes::fileTypes())->not->toBeEmpty()
        ->and(ContentTypes::has('note'))->toBeTrue()
        ->and(ContentTypes::has('page'))->toBeTrue()
        ->and(ContentTypes::modelFor('note'))->toBeString()
        ->and(ContentTypes::modelFor('missing'))->toBeNull();
});

it('excludes pages from csv types', function () {
    expect(ContentTypes::csvTypes())->not->toHaveKey('page')
        ->and(ContentTypes::csvTypes())->toHaveKey('note')
        ->and(array_values(ContentTypes::csvTypes()))->not->toContain(Page::class);
});

it('builds index models with fuel station before fuel and spine tables last', function () {
    $models = ContentTypes::indexModels();

    expect($models)->each->toBeString();

    foreach ($models as $class) {
        expect(is_a($class, DefinesContentSchema::class, true))->toBeTrue(
            "{$class} must implement DefinesContentSchema",
        );
    }

    $fuelStation = array_search(FuelStation::class, $models, true);
    $fuel = array_search(Fuel::class, $models, true);
    $timeline = array_search(TimelineEntry::class, $models, true);

    expect($fuelStation)->toBeInt()
        ->and($fuel)->toBeInt()
        ->and($fuelStation)->toBeLessThan($fuel)
        ->and($timeline)->toBeGreaterThan($fuel);
});

it('includes every file type model in the index list', function () {
    $index = ContentTypes::indexModels();

    foreach (ContentTypes::fileTypes() as $class) {
        expect($index)->toContain($class);
    }
});
