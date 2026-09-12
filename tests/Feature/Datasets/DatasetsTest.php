<?php

use App\Datasets\Dataset;
use App\Datasets\Datasets;
use App\Enums\TimelineType;
use App\Models\User;
use App\Presenters\CardPresenter;
use App\Search\SearchSchema;
use App\Support\TypeCatalogue;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Str;

it('declares one dataset per timeline type, in enum order', function () {
    expect(array_keys(Datasets::all()))->toBe(array_column(TimelineType::cases(), 'value'));

    foreach (TimelineType::cases() as $case) {
        expect($case->dataset()->type())->toBe($case);
    }
});

it('finds a dataset by model instance and by class name', function () {
    foreach (Datasets::all() as $key => $dataset) {
        expect(Datasets::forModel($dataset->model())?->type()->value)->toBe($key)
            ->and(Datasets::forModel(new ($dataset->model()))?->type()->value)->toBe($key);
    }

    expect(Datasets::forModel(User::class))->toBeNull()
        ->and(Datasets::for('nope'))->toBeNull();
});

it('matches the catalogue row each type has today', function (Dataset $dataset) {
    $meta = TypeCatalogue::for($dataset->type()->value);

    expect($dataset->icon())->toBe($meta->icon)
        ->and($dataset->label())->toBe($meta->label)
        ->and($dataset->plural())->toBe($meta->plural)
        ->and('/'.$dataset->slug())->toBe($meta->href)
        ->and($dataset->keywords())->toBe($meta->keywords)
        ->and($dataset->eyebrow() ?? $dataset->label())->toBe($meta->eyebrow());
})->with(fn (): array => array_values(Datasets::all()));

it('matches the registry entry each type has today', function (Dataset $dataset) {
    $definition = TypeRegistry::all()[$dataset->type()->value];

    expect($dataset->model())->toBe($definition['model'])
        ->and($dataset->noun())->toBe($definition['noun'])
        ->and($dataset->stats())->toBe($definition['stats'])
        ->and($dataset->taxonomy() === null)->toBe($definition['taxonomy'] === null);

    if ($definition['taxonomy'] !== null) {
        $built = ($dataset->taxonomy())($dataset->model(), $dataset->slug());

        expect($built['base'])->toBe($definition['taxonomy']['base'])
            ->and($built['param'])->toBe($definition['taxonomy']['param'])
            ->and($built['label'])->toBe($definition['taxonomy']['label']);
    }
})->with(fn (): array => array_values(Datasets::all()));

it('feeds the search schema from each dataset', function (Dataset $dataset) {
    $key = $dataset->type()->value;
    $fields = array_diff_key(SearchSchema::types()[$key]['fields'], array_flip(['day', 'month', 'year']));

    expect(array_keys($fields))->toBe(array_keys($dataset->searchFields()))
        ->and(SearchSchema::textColumns()[$key] ?? [])->toBe($dataset->textColumns());
})->with(fn (): array => array_values(Datasets::all()));

it('presents with the card class the presenter uses today', function (Dataset $dataset) {
    $model = new ($dataset->model());

    expect($dataset->card()::class)->toBe(CardPresenter::card($model)::class);
})->with(fn (): array => array_values(Datasets::all()));

it('counts entries in the nouns the more page uses', function () {
    $nouns = array_map(fn (Dataset $dataset): array => $dataset->countNouns(), Datasets::all());

    expect($nouns)->toBe([
        'activity' => ['activity', 'activities'],
        'sleep' => ['night', 'nights'],
        'calorie' => ['day', 'days'],
        'media' => ['logged', 'logged'],
        'event' => ['event', 'events'],
        'appearance' => ['appearance', 'appearances'],
        'podcast' => ['episode', 'episodes'],
        'flight' => ['flight', 'flights'],
        'checkin' => ['check-in', 'check-ins'],
        'fuel' => ['fill-up', 'fill-ups'],
        'project' => ['project', 'projects'],
        'article' => ['article', 'articles'],
        'note' => ['note', 'notes'],
    ]);
});

it('groups every type into a kind', function () {
    $kinds = array_map(fn (Dataset $dataset): string => $dataset->kind()->value, Datasets::all());

    expect($kinds)->toBe([
        'activity' => 'health',
        'sleep' => 'health',
        'calorie' => 'health',
        'media' => 'watching',
        'event' => 'going-out',
        'appearance' => 'speaking',
        'podcast' => 'speaking',
        'flight' => 'travel',
        'checkin' => 'travel',
        'fuel' => 'travel',
        'project' => 'writing',
        'article' => 'writing',
        'note' => 'writing',
    ]);
});

it('publishes count nouns and kind to the frontend module', function () {
    foreach (Datasets::all() as $key => $dataset) {
        $row = TypeCatalogue::for($key)->toArray();

        expect($row['noun'])->toBe($dataset->countNouns()[0])
            ->and($row['nounPlural'])->toBe($dataset->countNouns()[1])
            ->and($row['kind'])->toBe($dataset->kind()->value);
    }
});

it('has an entry detail component for every dataset', function () {
    foreach (Datasets::all() as $key => $dataset) {
        $component = base_path('resources/js/Components/Entry/'.Str::studly($key).'Detail.vue');

        expect(file_exists($component))->toBeTrue("{$key} has no ".basename($component).'.');
    }
});

it('exposes every dataset table to the MCP database tools', function () {
    foreach (Datasets::all() as $key => $dataset) {
        $table = (new ($dataset->model()))->getTable();

        expect(in_array($table, config('mcp.tables'), true))->toBeTrue("{$key} stores rows in {$table}, which config/mcp.php does not expose.");
    }
});
