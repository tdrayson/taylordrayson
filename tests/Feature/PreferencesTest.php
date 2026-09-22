<?php

use App\Support\Preferences;
use Illuminate\Http\Request;

it('shares the visitor preferences with every page', function () {
    $this->withUnencryptedCookies([
        Preferences::THEME => 'dark',
        Preferences::SETTING_PREFIX.'distanceUnit' => 'km',
    ])->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('preferences.theme', 'dark')
            ->where('preferences.settings.distanceUnit', 'km'));
});

it('falls back to system and light when nothing is set', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('preferences.theme', 'system')
        ->where('preferences.scheme', 'light'));
});

it('rejects a theme cookie that is not one of the three', function () {
    // The cookie is written by the browser, so the value is untrusted.
    $this->withUnencryptedCookies([Preferences::THEME => '"><script>'])
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('preferences.theme', 'system'));
});

it('renders the dark class server-side so the first paint matches', function () {
    $this->withUnencryptedCookies([Preferences::THEME => 'dark'])
        ->get('/')
        ->assertSee('<html lang="en" class="dark"', false);

    $this->withUnencryptedCookies([Preferences::THEME => 'light'])
        ->get('/')
        ->assertDontSee('<html lang="en" class="dark"', false);
});

it('resolves a system visitor from the scheme the client reported', function () {
    // 'system' cannot be resolved server-side, so the client writes back what
    // it actually resolved to and the next request renders that.
    $this->withUnencryptedCookies([Preferences::THEME => 'system', Preferences::SCHEME => 'dark'])
        ->get('/')
        ->assertSee('<html lang="en" class="dark"', false);
});

it('writes a Celsius reading in the visitor temperature unit', function () {
    $fahrenheit = Request::create('/', cookies: [Preferences::SETTING_PREFIX.'temperatureUnit' => 'f']);

    expect(Preferences::temperature(Request::create('/'), 18.4))->toBe('18°C')
        ->and(Preferences::temperature($fahrenheit, 18.4))->toBe('65°F')
        ->and(Preferences::temperature($fahrenheit, -40))->toBe('-40°F');
});
