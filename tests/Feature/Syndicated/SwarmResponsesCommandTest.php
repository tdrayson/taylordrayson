<?php

use App\Enums\Source;
use App\Models\Checkin;
use App\Models\SyndicatedResponse;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.foursquare.access_token' => 'test-token']);
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);
});

/** One page of check-ins, then the empty page that ends pagination. */
function fakeSwarmPage(array $items): void
{
    Saloon::fake([
        MockResponse::make(['response' => ['checkins' => ['items' => $items]]]),
        MockResponse::make(['response' => ['checkins' => ['items' => []]]]),
    ]);
}

it('stores the likes reported for a check-in it already holds', function () {
    $checkin = Checkin::factory()->create([
        'source' => Source::Swarm->value, 'source_id' => 'abc', 'occurred_at' => now()->subDay(),
    ]);

    fakeSwarmPage([[
        'id' => 'abc',
        'likes' => ['count' => 1, 'groups' => [['items' => [['id' => '1', 'displayName' => 'Luke Allen']]]]],
        'comments' => ['count' => 0],
    ]]);

    $this->artisan('swarm:responses')->assertSuccessful();

    expect($checkin->syndicatedResponses()->sole()->author_name)->toBe('Luke Allen');
});

it('ignores a check-in that is not stored here', function () {
    fakeSwarmPage([[
        'id' => 'not-ours',
        'likes' => ['count' => 1, 'groups' => [['items' => [['id' => '1', 'displayName' => 'Luke Allen']]]]],
    ]]);

    $this->artisan('swarm:responses')->assertSuccessful();

    expect(SyndicatedResponse::query()->count())->toBe(0);
});
