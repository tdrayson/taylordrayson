<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('takes a URL in the block itself rather than a browser prompt', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->assertScript('document.querySelector(\'.prose-editor input[type="url"]\') !== null', true);
});

it('holds a YouTube URL behind its thumbnail until play is pressed', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->fill('.prose-editor input[type="url"]', 'https://www.youtube.com/watch?v=WfKNVgvZA74');
    $page->keys('.prose-editor input[type="url"]', ['Enter']);

    // Nothing of YouTube's is loaded yet: the still comes off an image host.
    $page->assertScript("document.querySelector('.prose-editor iframe') !== null", false)
        ->assertScript(
            "document.querySelector('.prose-editor button[aria-label^=\"Play video\"] img')?.src",
            'https://i.ytimg.com/vi/WfKNVgvZA74/maxresdefault.jpg',
        );

    $page->click('.prose-editor button[aria-label^="Play video"]');

    $page->assertScript(
        "document.querySelector('.prose-editor iframe')?.getAttribute('src')",
        'https://www.youtube-nocookie.com/embed/WfKNVgvZA74?rel=0&autoplay=1',
    );
});

it('plays a direct file URL in a video element, fetching nothing up front', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->fill('.prose-editor input[type="url"]', 'https://example.com/clip.mp4');
    $page->keys('.prose-editor input[type="url"]', ['Enter']);

    $page->assertScript("document.querySelector('.prose-editor video')?.preload", 'none')
        ->assertScript("document.querySelector('.prose-editor iframe') !== null", false);
});

it('says so when the URL is not something it can play', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->fill('.prose-editor input[type="url"]', 'https://example.com/not-a-video');
    $page->keys('.prose-editor input[type="url"]', ['Enter']);

    $page->assertSee('will not play');
});
