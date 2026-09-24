<?php

use App\Datasets\Datasets;
use App\Mcp\Tools\Timeline;
use App\Models\Flight;
use App\Search\FilterValidator;
use App\Support\OgRenderer;
use Illuminate\Support\Facades\Storage;

it('resolves a live key to its dataset', function () {
    expect(Datasets::for('flight')?->type()->value)->toBe('flight');
});

it('resolves nothing for an unknown key', function () {
    expect(Datasets::for('nope'))->toBeNull();
});

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

it('errors on a retired dataset key via the mcp timeline tool', function () {
    $result = callTool(Timeline::class, ['from' => now()->toDateString(), 'type' => 'podcast']);

    expect($result['error'])->toBeTrue()
        ->and($result['text'])->toContain('No type called podcast');
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

it('drops an advanced search filter group keyed by a retired dataset key', function () {
    $raw = json_encode([['type' => 'podcast', 'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'x']]]]);

    expect((new FilterValidator)($raw))->toBe([]);
});

it('treats calorie as an unknown type', function () {
    expect(Datasets::for('calorie'))->toBeNull();

    $this->get('/feed?types=calorie')->assertOk();
});

it('treats checkin as an unknown type', function () {
    expect(Datasets::for('checkin'))->toBeNull();

    $this->get('/feed?types=checkin')->assertOk();
});

it('treats podcast as an unknown type', function () {
    expect(Datasets::for('podcast'))->toBeNull();

    $this->get('/feed?types=podcast')->assertOk();
});
