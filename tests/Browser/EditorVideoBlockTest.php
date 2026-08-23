<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('takes a URL in the block itself rather than a browser prompt', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->assertScript('document.querySelector(\'.prose-editor input[type="url"]\') !== null', true);
});

it('plays a YouTube URL as a nocookie embed', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->fill('.prose-editor input[type="url"]', 'https://www.youtube.com/watch?v=WfKNVgvZA74');
    $page->keys('.prose-editor input[type="url"]', ['Enter']);

    $page->assertScript(
        "document.querySelector('.prose-editor iframe')?.getAttribute('src')",
        'https://www.youtube-nocookie.com/embed/WfKNVgvZA74?rel=0',
    );
});

it('plays a direct file URL in a video element', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/video');
    $page->keys('.prose-editor', ['Enter']);

    $page->fill('.prose-editor input[type="url"]', 'https://example.com/clip.mp4');
    $page->keys('.prose-editor input[type="url"]', ['Enter']);

    $page->assertScript("document.querySelector('.prose-editor video') !== null", true)
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
