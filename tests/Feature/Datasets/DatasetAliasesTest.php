<?php

use App\Datasets\Datasets;
use App\Mcp\Tools\Timeline;
use App\Models\Flight;
use App\Support\OgRenderer;
use Illuminate\Support\Facades\Storage;

it('resolves a live key to its dataset', function () {
    expect(array_map(fn ($dataset) => $dataset->type()->value, Datasets::resolve('flight')))->toBe(['flight'])
        ->and(Datasets::resolveOne('flight')?->type()->value)->toBe('flight');
});

it('resolves nothing for an unknown key', function () {
    expect(Datasets::resolve('nope'))->toBe([])
        ->and(Datasets::resolveOne('nope'))->toBeNull();
});

it('points every alias at live keys only', function () {
    expect(Datasets::ALIASES)->toBeArray();

    foreach (Datasets::ALIASES as $alias => $targets) {
        expect(Datasets::for($alias))->toBeNull("{$alias} is both a live key and an alias.");

        foreach ($targets as $target) {
            expect(Datasets::for($target))->not->toBeNull("{$alias} points at {$target}, which is not a dataset.");
        }
    }
});

// A PHPUnit data provider errors on an empty set rather than skipping (unlike
// Pest's own ->skip()), so this test only registers its dataset once aliases
// exist; until Task 3 it is skipped outright.
$aliasPairs = collect(Datasets::ALIASES)
    ->flatMap(fn (array $targets, string $alias): array => count($targets) === 1 ? [[$alias, $targets[0]]] : [])
    ->values()->all();

if ($aliasPairs !== []) {
    it('accepts every alias wherever a type key comes from outside', function (string $alias, string $target) {
        expect(Datasets::resolveOne($alias)?->type()->value)->toBe($target);
    })->with($aliasPairs);
} else {
    it('accepts every alias wherever a type key comes from outside')->skip('no aliases defined yet (added in Task 3)');
}

it('filters the feed to a live key via ?types=', function () {
    // Covered end to end by FeedBuilderTest ("filters to a single type via the
    // types parameter"), which exercises this same requestedModels() boundary.
})->skip('covered by tests/Feature/FeedBuilderTest.php');

it('returns flights for a live key via the mcp timeline tool', function () {
    Flight::factory()->create(['occurred_at' => now()]);

    $result = callTool(Timeline::class, ['from' => now()->toDateString(), 'type' => 'flight']);

    expect($result['data']['count'])->toBe(1)
        ->and($result['data']['entries'][0]['type'])->toBe('flight');
});

it('serves the og preview for a live key', function () {
    Storage::fake('local');
    $directory = 'og/'.OgRenderer::generation().'/preview';
    Storage::disk('local')->put($directory.'/'.md5('flight').'.png', 'fake-png-bytes');

    $this->get('/og/preview/flight.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('runs an advanced search filter for a live key', function () {
    // Covered end to end by AdvancedSearchTest ("ANDs conditions within a group
    // (flights over 300mi with easyJet)"), which exercises FilterValidator's
    // group type resolution through the same boundary.
})->skip('covered by tests/Feature/AdvancedSearchTest.php');
