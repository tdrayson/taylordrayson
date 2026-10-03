<?php

use App\Data\Head\HeadData;
use App\Data\Head\LinkTagData;
use App\Data\Head\MetaTagData;
use App\Enums\MetaAttribute;
use App\Support\Head;
use Tests\TestCase;

uses(TestCase::class);

it('falls back to the site default when no page defines its head', function () {
    $head = (new Head)->resolve();

    expect($head->title)->toBeNull()
        ->and($head->description)->toBe(config('identity.bio'))
        ->and($head->image)->toStartWith(url('/og.png'));
});

it('lets the hooks override the page definition', function () {
    $resolved = (new Head)
        ->set(new HeadData(title: 'Page', description: 'About the page', canonical: 'https://example.com/page'))
        ->canonical('https://example.com/elsewhere')
        ->noindex()
        ->resolve();

    expect($resolved)
        ->title->toBe('Page')
        ->canonical->toBe('https://example.com/elsewhere')
        ->noindex->toBeTrue();
});

it('keeps one tag per key, the last written winning', function () {
    $resolved = (new Head)
        ->set(new HeadData(
            title: 'Page',
            description: 'About the page',
            meta: [new MetaTagData(MetaAttribute::Name, 'author', 'From the page')],
            links: [new LinkTagData('alternate', '/page.json', 'application/json', 'First')],
        ))
        ->meta(MetaAttribute::Name, 'author', 'From a hook')
        ->meta(MetaAttribute::Property, 'author', 'A different attribute')
        ->link('alternate', '/page.json', 'application/json', 'Second')
        ->link('alternate', '/page.json', 'text/plain')
        ->resolve();

    expect(array_map(fn (MetaTagData $tag): string => $tag->content, $resolved->meta))
        ->toBe(['From a hook', 'A different attribute'])
        ->and(array_map(fn (LinkTagData $link): ?string => $link->title ?? $link->type, $resolved->links))
        ->toBe(['Second', 'text/plain']);
});

it('returns a new head rather than changing the definition', function () {
    $definition = new HeadData(title: 'Page', description: 'About the page');

    (new Head)->set($definition)->noindex()->link('me', 'https://github.com/tdrayson')->resolve();

    expect($definition->noindex)->toBeFalse()->and($definition->links)->toBe([]);
});

it('refuses tags the typed fields own', function (Closure $write) {
    $write(new Head);
})->throws(InvalidArgumentException::class)->with([
    'description' => [fn (Head $head) => $head->meta(MetaAttribute::Name, 'description', 'x')],
    'robots' => [fn (Head $head) => $head->meta(MetaAttribute::Name, 'Robots', 'x')],
    'open graph' => [fn (Head $head) => $head->meta(MetaAttribute::Property, 'og:title', 'x')],
    'twitter' => [fn (Head $head) => $head->meta(MetaAttribute::Name, 'twitter:card', 'x')],
    'canonical' => [fn (Head $head) => $head->link('canonical', 'https://example.com')],
]);

it('builds a deferred definition only when the head is resolved', function () {
    $built = 0;

    $head = (new Head)->set(function () use (&$built): HeadData {
        $built++;

        return new HeadData(title: 'Deferred', description: 'Built late');
    });

    expect($built)->toBe(0)
        ->and($head->resolve()->title)->toBe('Deferred')
        ->and($built)->toBe(1);
});
