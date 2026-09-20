<?php

use App\Actions\Citations\FetchCitation;
use App\Models\Citation;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * A citation is read from a URL a stranger chose, the same hostile input the
 * webmention endpoint takes, so it goes through SafeFetch on the same terms.
 * Each page here is faked with something worth stealing: if the guard ever
 * stops holding, the fetch succeeds and these fail on the returned value
 * rather than quietly on a stray request.
 */

it('refuses a scheme that is not http or https', function (string $url) {
    Http::fake(['*' => Http::response('<title>Should never be read</title>')]);

    expect(app(FetchCitation::class)($url))->toBeNull();

    Http::assertNothingSent();
})->with([
    'ftp' => 'ftp://example.com/post',
    'file' => 'file:///etc/passwd',
    'gopher' => 'gopher://example.com/post',
]);

it('refuses a private or loopback address', function (string $url) {
    Http::fake(['*' => Http::response('<title>Cloud metadata</title>')]);

    expect(app(FetchCitation::class)($url))->toBeNull();

    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1/metadata',
    'link-local, where cloud metadata lives' => 'http://169.254.169.254/latest/meta-data/',
    'private range' => 'http://192.168.1.1/admin',
    'this-network' => 'http://0.0.0.0/',
]);

// The body is read against a ceiling because the page decides how much it
// sends, and a queue worker that buffers all of it is one large response away
// from being killed.
it('refuses a body past the ceiling rather than buffering it', function () {
    $huge = '<title>A real title</title>'.str_repeat('x', 2 * 1024 * 1024);

    Http::fake(['https://example.com/huge' => Http::response($huge)]);

    expect(app(FetchCitation::class)('https://example.com/huge'))->toBeNull();
});

// SafeUrl passing is not enough on its own: the host that answers chooses where
// to send us next, and the client would follow it without being asked not to.
it('will not follow a redirect into a private address', function () {
    Http::fake([
        'https://example.com/redirector' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        'http://169.254.169.254/*' => Http::response('<title>Cloud metadata</title>'),
    ]);

    expect(app(FetchCitation::class)('https://example.com/redirector'))->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
});

// The editor's preview is the surface a URL actually arrives on, so the guard
// has to hold there too, and nothing may be stored for a page never read.
it('stores nothing and fetches nothing when the preview is pointed at a private address', function () {
    Http::fake(['*' => Http::response('<title>Cloud metadata</title>')]);

    $this->actingAs(User::factory()->create())
        ->postJson('/citations/preview', ['url' => 'http://169.254.169.254/latest/meta-data/', 'kind' => 'reply'])
        ->assertOk()
        ->assertJsonPath('data.cited', null);

    Http::assertNothingSent();
    expect(Citation::query()->count())->toBe(0);
});
