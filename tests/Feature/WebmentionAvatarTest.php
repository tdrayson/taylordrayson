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

afterEach(function () {
    File::deleteDirectory(public_path('avatars'));
});

it('keeps the photo it has when a refresh cannot be fetched', function () {
    $url = 'https://example.com/me.png';

    Http::fake([$url => Http::response(pixel(), 200, ['content-type' => 'image/png'])]);

    $path = app(StoreAuthorPhoto::class)($url);

    expect($path)->not->toBeNull()
        ->and(File::exists(public_path($path)))->toBeTrue();

    $before = File::get(public_path($path));

    // The monthly refresh runs against a server that is now down. A cosmetic
    // re-fetch must never cost us the face we already have.
    Http::fake([$url => Http::response('', 500)]);

    expect(app(StoreAuthorPhoto::class)($url, refresh: true))->toBe($path)
        ->and(File::get(public_path($path)))->toBe($before);
});

it('refuses anything that is not an image, however it is labelled', function () {
    $url = 'https://example.com/payload.png';

    Http::fake([$url => Http::response('<script>alert(1)</script>', 200, ['content-type' => 'text/html'])]);

    expect(app(StoreAuthorPhoto::class)($url))->toBeNull();
});
