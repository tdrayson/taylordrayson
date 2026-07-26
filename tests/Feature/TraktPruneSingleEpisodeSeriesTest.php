<?php

use App\Models\Media;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.trakt.client_id', 'test-client-id');
    config()->set('services.trakt.client_secret', 'test-client-secret');
    config()->set('services.trakt.username', 'taylor');
});

function seedSingleEpisodeSeries(): array
{
    // Money Heist: in the manifest, one episode, should go entirely.
    $dropped = Series::create(['trakt_id' => 71446, 'slug' => 'money-heist', 'title' => 'Money Heist', 'year' => 2017]);
    Media::create([
        'occurred_at' => '2018-03-26 21:15:00', 'type' => 'episode', 'title' => 'Efectuar lo acordado',
        'source' => 'trakt', 'source_id' => '8582913302', 'series_id' => $dropped->id, 'timezone' => 'Europe/London',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    // A Small Light: deliberately NOT in the manifest, must survive untouched.
    $kept = Series::create(['trakt_id' => 197234, 'slug' => 'a-small-light', 'title' => 'A Small Light', 'year' => 2023]);
    Media::create([
        'occurred_at' => '2026-07-17 22:32:00', 'type' => 'episode', 'title' => 'Pilot',
        'source' => 'trakt', 'source_id' => '13926121104', 'series_id' => $kept->id, 'timezone' => 'Europe/London',
        'meta' => ['season' => 1, 'episode' => 1],
    ]);

    return [$dropped, $kept];
}

function fakeTraktAuthAnd(array $removeResponse): void
{
    Http::fake([
        'api.trakt.tv/oauth/device/code' => Http::response([
            'device_code' => 'dev', 'user_code' => 'ABCD', 'verification_url' => 'https://trakt.tv/activate',
            'expires_in' => 30, 'interval' => 1,
        ]),
        'api.trakt.tv/oauth/device/token' => Http::response(['access_token' => 'tok-abc']),
        'api.trakt.tv/sync/history/remove' => Http::response($removeResponse),
    ]);
}

it('changes nothing on a dry run', function () {
    seedSingleEpisodeSeries();
    Http::fake();

    $this->artisan('trakt:prune-single-episode-series')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Media::count())->toBe(2)->and(Series::count())->toBe(2);
    Http::assertNothingSent();
});

it('removes the play and the series it emptied', function () {
    [$dropped, $kept] = seedSingleEpisodeSeries();
    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => []]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'yes')
        ->assertSuccessful();

    expect(Media::where('source_id', '8582913302')->exists())->toBeFalse()
        ->and(Series::find($dropped->id))->toBeNull()
        ->and(Series::find($kept->id))->not->toBeNull();
});

it('leaves a series alone when it still has episodes left', function () {
    [$dropped] = seedSingleEpisodeSeries();

    // A second episode on the same series that is NOT in the manifest.
    Media::create([
        'occurred_at' => '2018-03-27 21:15:00', 'type' => 'episode', 'title' => 'Imprudencias letales',
        'source' => 'trakt', 'source_id' => '9999999999', 'series_id' => $dropped->id, 'timezone' => 'Europe/London',
        'meta' => ['season' => 1, 'episode' => 2],
    ]);

    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => []]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'yes')
        ->assertSuccessful();

    expect(Series::find($dropped->id))->not->toBeNull()
        ->and(Media::where('series_id', $dropped->id)->count())->toBe(1);
});

it('never touches A Small Light', function () {
    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => []]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'yes')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return ! str_contains($request->url(), 'history/remove')
            || ! in_array(13926121104, (array) $request['ids'], true);
    });
});

it('keeps the local row when trakt reports the play as not found', function () {
    [$dropped] = seedSingleEpisodeSeries();
    fakeTraktAuthAnd(['deleted' => ['episodes' => 12], 'not_found' => ['ids' => [8582913302]]]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'yes')
        ->assertSuccessful();

    // Still on Trakt, so clearing it locally would let a full sync
    // resurrect it silently rather than surfacing the mismatch.
    expect(Media::where('source_id', '8582913302')->exists())->toBeTrue()
        ->and(Series::find($dropped->id))->not->toBeNull();
});
