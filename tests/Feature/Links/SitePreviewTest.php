<?php

use App\Links\LinkResolvers;
use App\Models\Activity;

/** The preview a path resolves to, or null. */
function previewFor(string $path): ?array
{
    return app(LinkResolvers::class)->resolve($path)?->toArray();
}

it('previews the site\'s own pages from the metadata they publish', function () {
    // Title and description come from OgMeta, so a page cannot describe itself
    // one way in its head and another way on a hover card.
    expect(previewFor('/more'))->not->toBeNull()
        ->and(previewFor('/on-this-day'))->not->toBeNull()
        ->and(previewFor('/photos')['title'])->toBe('Every photo, in one place')
        ->and(previewFor('/feeds')['excerpt'])->not->toBeEmpty()
        ->and(previewFor('/'))->not->toBeNull();
});

it('lets a literal route beat the taxonomy value it looks like', function () {
    // /media/tv is the series index, and 'tv' is also a media taxonomy value;
    // /flights/map is the globe, not an airline. Same collision routes/web.php
    // orders around.
    expect(previewFor('/media/tv')['title'])->toBe('Every series I have watched')
        ->and(previewFor('/flights/map')['title'])->toBe('Every flight on one globe');
});

it('previews a taxonomy value with how much is behind it', function () {
    Activity::factory()->count(3)->create(['type' => 'run']);

    expect(previewFor('/activities/run'))
        ->title->toBe('Run')
        ->type->toBe('activity')
        ->excerpt->toContain('activit');
});

it('gives nothing for a taxonomy value that does not exist', function () {
    expect(previewFor('/activities/not-a-real-type'))->toBeNull();
});
