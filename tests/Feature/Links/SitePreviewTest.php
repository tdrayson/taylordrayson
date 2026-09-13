<?php

use App\Links\LinkResolvers;
use App\Links\Resolvers\ArchiveResolver;
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

it('previews the tv shows page and the flights map as their own fixed pages', function () {
    expect(previewFor('/tv-shows')['title'])->toBe('Every show I have watched')
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

it('resolves the tv episode archive and the tv shows page as separate, non-colliding routes', function () {
    expect(app(ArchiveResolver::class)->resolve('/tv-episodes'))->not->toBeNull()
        ->and(app(ArchiveResolver::class)->resolve('/tv-shows'))->toBeNull()
        ->and(previewFor('/tv-shows')['title'])->toBe('Every show I have watched');
});
