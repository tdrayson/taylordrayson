<?php

use App\Services\Strava\Client;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(fn () => config(['services.strava.refresh_token' => 'test-token']));

it('reads the athletes who gave kudos', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/activities/778/kudos' => MockResponse::make([
            ['firstname' => 'Justin', 'lastname' => 'M.'],
        ]),
    ]);

    expect(app(Client::class)->kudos(778))->toBe([['firstname' => 'Justin', 'lastname' => 'M.']]);
});

it('reads the comments left on an activity', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/activities/778/comments' => MockResponse::make([
            ['id' => 2257139458, 'text' => 'Nice one', 'created_at' => '2025-08-27T09:44:33Z'],
        ]),
    ]);

    expect(app(Client::class)->comments(778))->toHaveCount(1);
});

// Strava defaults to 30 per page with no per_page sent, which silently
// truncates an activity with more kudos or comments than that.
it('asks Strava for up to 200 kudos and comments, not the default 30', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/activities/778/kudos' => MockResponse::make([]),
        '/api/v3/activities/778/comments' => MockResponse::make([]),
    ]);

    app(Client::class)->kudos(778);
    app(Client::class)->comments(778);

    Saloon::assertSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/kudos')
        && $request->query()->get('per_page') === 200);
    Saloon::assertSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/comments')
        && $request->query()->get('per_page') === 200);
});
