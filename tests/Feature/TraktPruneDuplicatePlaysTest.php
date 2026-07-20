<?php

use App\Exceptions\TraktException;
use App\Models\Media;
use App\Models\Series;
use App\Services\Trakt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.trakt.client_id', 'test-client-id');
    config()->set('services.trakt.client_secret', 'test-client-secret');
    config()->set('services.trakt.username', 'taylor');
});

/**
 * The two Breaking Bad plays from the real manifest, so the fixture exercises
 * the ids the command actually ships with rather than invented ones.
 */
function seedPlayPair(): Series
{
    $series = Series::create(['trakt_id' => 1388, 'slug' => 'breaking-bad', 'title' => 'Breaking Bad', 'year' => 2008]);

    foreach ([['8677547398', '2022-11-20 21:56:00'], ['9283695329', '2023-10-19 09:05:00']] as [$playId, $at]) {
        Media::create([
            'occurred_at' => $at,
            'type' => 'episode',
            'title' => 'One Minute',
            'source' => 'trakt',
            'source_id' => $playId,
            'series_id' => $series->id,
            'timezone' => 'Europe/London',
            'meta' => ['season' => 3, 'episode' => 7, 'ids' => ['trakt' => 73482]],
        ]);
    }

    return $series;
}

it('sends play ids and the bearer token to the removal endpoint', function () {
    Http::fake(['api.trakt.tv/sync/history/remove' => Http::response(['deleted' => ['episodes' => 2], 'not_found' => []])]);

    app(Trakt::class)->removeHistory(['9283695329', 8588872776], 'tok-123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.trakt.tv/sync/history/remove'
            && $request->method() === 'POST'
            && $request['ids'] === [9283695329, 8588872776]
            && $request->header('Authorization')[0] === 'Bearer tok-123'
            && $request->header('trakt-api-key')[0] === 'test-client-id';
    });
});

it('does not call trakt when there is nothing to remove', function () {
    Http::fake();

    expect(app(Trakt::class)->removeHistory([], 'tok-123'))
        ->toBe(['deleted' => ['movies' => 0, 'episodes' => 0], 'not_found' => []]);

    Http::assertNothingSent();
});

it('throws when trakt refuses the removal', function () {
    Http::fake(['api.trakt.tv/sync/history/remove' => Http::response('denied', 401)]);

    expect(fn () => app(Trakt::class)->removeHistory([123], 'bad-token'))
        ->toThrow(TraktException::class);
});

it('keeps polling while authorisation is pending, then returns the token', function () {
    Http::fakeSequence('api.trakt.tv/oauth/device/token')
        ->push('pending', 400)
        ->push(['access_token' => 'tok-abc'], 200);

    $token = app(Trakt::class)->pollForDeviceToken('dev-code', interval: 1, expiresIn: 30);

    expect($token)->toBe('tok-abc');
});

it('stops immediately when authorisation is denied', function () {
    Http::fake(['api.trakt.tv/oauth/device/token' => Http::response('denied', 418)]);

    expect(fn () => app(Trakt::class)->pollForDeviceToken('dev-code', interval: 1, expiresIn: 30))
        ->toThrow(TraktException::class, 'denied');
});

it('refuses to poll without a client secret', function () {
    config()->set('services.trakt.client_secret', null);
    Http::fake();

    expect(fn () => app(Trakt::class)->pollForDeviceToken('dev-code', interval: 1, expiresIn: 5))
        ->toThrow(TraktException::class, 'TRAKT_CLIENT_SECRET');

    Http::assertNothingSent();
});

it('changes nothing on a dry run', function () {
    seedPlayPair();
    Http::fake();

    $this->artisan('trakt:prune-duplicate-plays')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Media::count())->toBe(2);
    Http::assertNothingSent();
});

it('changes nothing when the confirmation is declined', function () {
    seedPlayPair();
    Http::fake();

    $this->artisan('trakt:prune-duplicate-plays --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'no')
        ->assertSuccessful();

    expect(Media::count())->toBe(2);
    Http::assertNothingSent();
});

it('deletes the spurious local row and keeps the genuine one', function () {
    seedPlayPair();

    Http::fake([
        'api.trakt.tv/oauth/device/code' => Http::response([
            'device_code' => 'dev', 'user_code' => 'ABCD', 'verification_url' => 'https://trakt.tv/activate',
            'expires_in' => 30, 'interval' => 1,
        ]),
        'api.trakt.tv/oauth/device/token' => Http::response(['access_token' => 'tok-abc']),
        'api.trakt.tv/sync/history/remove' => Http::response(['deleted' => ['episodes' => 17], 'not_found' => []]),
    ]);

    $this->artisan('trakt:prune-duplicate-plays --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'yes')
        ->assertSuccessful();

    expect(Media::where('source_id', '9283695329')->exists())->toBeFalse()
        ->and(Media::where('source_id', '8677547398')->exists())->toBeTrue();
});

it('leaves local rows alone when trakt reports the play as not found', function () {
    seedPlayPair();

    Http::fake([
        'api.trakt.tv/oauth/device/code' => Http::response([
            'device_code' => 'dev', 'user_code' => 'ABCD', 'verification_url' => 'https://trakt.tv/activate',
            'expires_in' => 30, 'interval' => 1,
        ]),
        'api.trakt.tv/oauth/device/token' => Http::response(['access_token' => 'tok-abc']),
        'api.trakt.tv/sync/history/remove' => Http::response([
            'deleted' => ['episodes' => 16],
            'not_found' => ['ids' => [9283695329]],
        ]),
    ]);

    $this->artisan('trakt:prune-duplicate-plays --force')
        ->expectsConfirmation('Delete these plays from your Trakt history?', 'yes')
        ->assertSuccessful();

    // Still on Trakt, so a full sync would re-import it. Deleting locally
    // would turn a visible mismatch into a silent reappearance.
    expect(Media::where('source_id', '9283695329')->exists())->toBeTrue();
});
