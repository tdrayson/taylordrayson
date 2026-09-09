<?php

use App\Actions\Files\ResolveZightVideo;
use Illuminate\Support\Facades\Http;

$share = 'https://share.getcloudapp.com/017ccc16';
$content = 'https://share.getcloudapp.com/items/017ccc16-9ff8-7e53-aca5-8af85be9c607/content_link';
$poster = 'https://thumbnail.cdn.zight.com/items/017ccc16/thumbnail.png';

/**
 * A Zight share page, carrying the head Zight really serves.
 */
function zightSharePage(string $content, string $poster): string
{
    return <<<HTML
    <!doctype html><html><head>
    <meta property="og:title" content="Screen Recording" />
    <meta property="og:video" content="{$content}" />
    <meta property="og:image" content="{$poster}" />
    </head><body></body></html>
    HTML;
}

it('resolves a share page to its content link and poster', function () use ($share, $content, $poster) {
    Http::fake(['share.getcloudapp.com/*' => Http::response(zightSharePage($content, $poster))]);

    $video = app(ResolveZightVideo::class)($share);

    expect($video->url)->toBe($content)
        ->and($video->poster)->toBe($poster)
        ->and($video->toArray())->toBe(['url' => $content, 'poster' => $poster]);
});

it('returns null for a share that no longer exists', function () use ($share) {
    Http::fake(['share.getcloudapp.com/*' => Http::response('Not Found', 404)]);

    expect(app(ResolveZightVideo::class)($share))->toBeNull();
});

it('returns null for a page carrying no video', function () use ($share) {
    Http::fake(['share.getcloudapp.com/*' => Http::response('<html><head><meta property="og:title" content="Nothing" /></head></html>')]);

    expect(app(ResolveZightVideo::class)($share))->toBeNull();
});

it('returns null for a url that is not a zight share, without asking', function () {
    Http::fake();

    expect(app(ResolveZightVideo::class)('https://youtube.com/watch?v=abc123'))->toBeNull();

    Http::assertNothingSent();
});

// The page's own JSON carries a signed CDN address that expires, so a video
// with no og:video is dropped rather than written into a post as a link that
// will 403 later.
it('ignores the signed address in the page json', function () use ($share) {
    $json = str_replace('/', '\/', 'https://p-123456.t2.n0.cdn.zight.com/items/abc/video.mp4?v=9&Signature=xyz');

    Http::fake(['share.getcloudapp.com/*' => Http::response('<html><body><script>{"content_url":"'.$json.'"}</script></body></html>')]);

    expect(app(ResolveZightVideo::class)($share))->toBeNull();
});

it('fetches a share page once however often the import runs', function () use ($share, $content, $poster) {
    Http::fake(['share.getcloudapp.com/*' => Http::response(zightSharePage($content, $poster))]);

    app(ResolveZightVideo::class)($share);
    $video = app(ResolveZightVideo::class)($share);

    expect($video->url)->toBe($content)
        ->and($video->poster)->toBe($poster);

    Http::assertSentCount(1);
});
