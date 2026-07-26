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

// Money Heist S1E1's real episode trakt id; the command removes by this, not by play id.
const MONEY_HEIST_EPISODE_TRAKT_ID = 2561737;

function seedSingleEpisodeSeries(): array
{
    // Money Heist: in the manifest, one episode, should go entirely.
    $dropped = Series::create(['trakt_id' => 71446, 'slug' => 'money-heist', 'title' => 'Money Heist', 'year' => 2017]);
    Media::create([
        'occurred_at' => '2018-03-26 21:15:00', 'type' => 'episode', 'title' => 'Efectuar lo acordado',
        'source' => 'trakt', 'source_id' => '8582913302', 'series_id' => $dropped->id, 'timezone' => 'Europe/London',
        'meta' => ['season' => 1, 'episode' => 1, 'ids' => ['trakt' => MONEY_HEIST_EPISODE_TRAKT_ID]],
    ]);

    // A Small Light: deliberately NOT in the manifest, must survive untouched.
    $kept = Series::create(['trakt_id' => 197234, 'slug' => 'a-small-light', 'title' => 'A Small Light', 'year' => 2023]);
    Media::create([
        'occurred_at' => '2026-07-17 22:32:00', 'type' => 'episode', 'title' => 'Pilot',
        'source' => 'trakt', 'source_id' => '13926121104', 'series_id' => $kept->id, 'timezone' => 'Europe/London',
        'meta' => ['season' => 1, 'episode' => 1, 'ids' => ['trakt' => 4834902]],
    ]);

    return [$dropped, $kept];
}

/**
 * Fake the auth flow and history endpoints.
 *
 * $stillPresentEpisodeIds are the episode trakt ids the AUTHENTICATED history
 * reports as remaining after the removal attempt - the reverify read that now
 * drives which local rows are cleared. Default empty: everything asked for is
 * gone.
 *
 * @param  array<int, int>  $stillPresentEpisodeIds
 */
function fakeTraktAuthAnd(array $removeResponse, string $username = 'taylor', array $stillPresentEpisodeIds = []): void
{
    $history = array_map(fn (int $id): array => ['id' => 1, 'episode' => ['ids' => ['trakt' => $id]]], $stillPresentEpisodeIds);

    Http::fake([
        'api.trakt.tv/oauth/device/code' => Http::response([
            'device_code' => 'dev', 'user_code' => 'ABCD', 'verification_url' => 'https://trakt.tv/activate',
            'expires_in' => 30, 'interval' => 1,
        ]),
        'api.trakt.tv/oauth/device/token' => Http::response(['access_token' => 'tok-abc']),
        'api.trakt.tv/users/settings' => Http::response(['user' => ['username' => $username]]),
        'api.trakt.tv/sync/history/remove' => Http::response($removeResponse),
        // Paginated: the seeded items on page 1, empty thereafter to end the loop.
        'api.trakt.tv/sync/history/episodes*' => fn ($request) => Http::response(
            ((int) ($request->data()['page'] ?? 1)) === 1 ? $history : []
        ),
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

it('removes the episode by trakt id and drops the series it emptied', function () {
    [$dropped, $kept] = seedSingleEpisodeSeries();
    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => ['episodes' => []]]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these episodes from your Trakt history?', 'yes')
        ->assertSuccessful();

    expect(Media::where('source_id', '8582913302')->exists())->toBeFalse()
        ->and(Series::find($dropped->id))->toBeNull()
        ->and(Series::find($kept->id))->not->toBeNull();

    // Removal targets the episode trakt id, not the history/play id.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'history/remove')
        && collect($request['episodes'] ?? [])->pluck('ids.trakt')->contains(MONEY_HEIST_EPISODE_TRAKT_ID));
});

it('leaves a series alone when it still has episodes left', function () {
    [$dropped] = seedSingleEpisodeSeries();

    // A second episode on the same series that is NOT in the manifest.
    Media::create([
        'occurred_at' => '2018-03-27 21:15:00', 'type' => 'episode', 'title' => 'Imprudencias letales',
        'source' => 'trakt', 'source_id' => '9999999999', 'series_id' => $dropped->id, 'timezone' => 'Europe/London',
        'meta' => ['season' => 1, 'episode' => 2, 'ids' => ['trakt' => 2561738]],
    ]);

    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => ['episodes' => []]]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these episodes from your Trakt history?', 'yes')
        ->assertSuccessful();

    expect(Series::find($dropped->id))->not->toBeNull()
        ->and(Media::where('series_id', $dropped->id)->count())->toBe(1);
});

it('aborts when the wrong trakt account authorises', function () {
    seedSingleEpisodeSeries();
    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => ['episodes' => []]], username: 'someone-else');

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these episodes from your Trakt history?', 'yes')
        ->assertFailed();

    // Wrong account: nothing removed locally, and history/remove is never called.
    expect(Media::where('source_id', '8582913302')->exists())->toBeTrue();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'history/remove'));
});

it('never targets A Small Light for removal', function () {
    seedSingleEpisodeSeries();
    fakeTraktAuthAnd(['deleted' => ['episodes' => 13], 'not_found' => ['episodes' => []]]);

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these episodes from your Trakt history?', 'yes')
        ->assertSuccessful();

    // A Small Light's episode id must never appear in the removal payload.
    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'history/remove')
        || ! collect($request['episodes'] ?? [])->pluck('ids.trakt')->contains(4834902));
    expect(Media::where('source_id', '13926121104')->exists())->toBeTrue();
});

it('keeps the local row when the episode is still in authenticated history', function () {
    [$dropped] = seedSingleEpisodeSeries();

    // The remove endpoint claims success, but the authenticated history still
    // lists the episode - so the removal did not really happen and the local
    // row must survive rather than be cleared on a false signal.
    fakeTraktAuthAnd(
        ['deleted' => ['episodes' => 13], 'not_found' => ['episodes' => []]],
        stillPresentEpisodeIds: [MONEY_HEIST_EPISODE_TRAKT_ID],
    );

    $this->artisan('trakt:prune-single-episode-series --force')
        ->expectsConfirmation('Delete these episodes from your Trakt history?', 'yes')
        ->assertSuccessful();

    expect(Media::where('source_id', '8582913302')->exists())->toBeTrue()
        ->and(Series::find($dropped->id))->not->toBeNull();
});
