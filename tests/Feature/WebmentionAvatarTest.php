<?php

use App\Actions\Webmentions\StoreAuthorPhoto;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** A real PNG, so the encoder has something it can actually open. */
function pixel(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

/** The first response stores a photo; the rest answer the refresh that follows. */
function servePhoto(string $url, array $thenReplyWith): void
{
    $sequence = Http::sequence()->push(pixel(), 200, [
        'content-type' => 'image/png',
        'etag' => '"abc123"',
    ]);

    foreach ($thenReplyWith as [$body, $status, $headers]) {
        $sequence->push($body, $status, $headers);
    }

    Http::fake([$url => $sequence]);
}

afterEach(function () {
    File::deleteDirectory(public_path('avatars'));
    File::deleteDirectory(storage_path('app/avatar-validators'));
});

it('keeps the photo it has when a refresh cannot be fetched', function () {
    $url = 'https://example.com/me.png';

    // Stored, then their server falls over on the monthly refresh.
    servePhoto($url, [['', 500, []]]);

    $action = app(StoreAuthorPhoto::class);
    $path = $action($url);
    $before = File::get(public_path($path));

    expect($action($url, refresh: true))->toBe($path)
        ->and($action->outcome)->toBe('unchanged')
        ->and(File::get(public_path($path)))->toBe($before);
});

it('asks with a validator and does no work when told nothing changed', function () {
    $url = 'https://example.com/me.png';

    servePhoto($url, [['', 304, []]]);

    $action = app(StoreAuthorPhoto::class);
    $path = $action($url);
    $written = File::lastModified(public_path($path));

    expect($action($url, refresh: true))->toBe($path)
        ->and($action->outcome)->toBe('unchanged')
        // Not re-encoded: a 304 should cost both ends nothing but headers.
        ->and(File::lastModified(public_path($path)))->toBe($written);

    Http::assertSent(fn ($request) => $request->hasHeader('If-None-Match', '"abc123"'));
});

it('replaces the photo when the image behind the same URL has changed', function () {
    $url = 'https://example.com/me.png';

    servePhoto($url, [[pixel(), 200, ['content-type' => 'image/png']]]);

    $action = app(StoreAuthorPhoto::class);
    $path = $action($url);

    expect($action($url, refresh: true))->toBe($path)
        ->and($action->outcome)->toBe('stored');
});

it('refuses anything that is not an image, however it is labelled', function () {
    $url = 'https://example.com/payload.png';

    Http::fake([$url => Http::response('<script>alert(1)</script>', 200, ['content-type' => 'text/html'])]);

    expect(app(StoreAuthorPhoto::class)($url))->toBeNull();
});

it('will not follow a photo redirect into the private network', function () {
    $url = 'https://example.com/me.png';

    Http::fake([
        $url => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '*' => Http::response('secrets', 200, ['content-type' => 'image/png']),
    ]);

    expect(app(StoreAuthorPhoto::class)($url))->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
});
