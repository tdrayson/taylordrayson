<?php

use App\Models\Article;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

function coverJpegBytes(): string
{
    $image = imagecreatetruecolor(1280, 720);
    imagefilledrectangle($image, 0, 0, 1279, 719, imagecolorallocate($image, 120, 90, 200));
    ob_start();
    imagejpeg($image, null, 80);

    return (string) ob_get_clean();
}

it('exposes the cover on the article entry payload and timeline card', function () {
    Storage::fake('public');

    $article = Article::factory()->create(['published' => true, 'occurred_at' => now()->subHour()]);
    $article->addMediaFromString(coverJpegBytes())->usingFileName('cover.jpg')->toMediaCollection('cover');

    get($article->fresh()->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('entry.cover.src')
            ->has('entry.cover.full'));

    get('/')->assertInertia(fn ($page) => $page
        ->where('groups.0.items.0.iconKey', 'article')
        ->has('groups.0.items.0.photos.0.src'));
});

it('returns a null cover when the article has none', function () {
    $article = Article::factory()->create(['published' => true, 'occurred_at' => now()->subHour()]);

    get($article->fresh()->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('entry.cover', null));
});
