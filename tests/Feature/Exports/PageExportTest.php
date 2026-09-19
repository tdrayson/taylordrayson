<?php

use App\Models\Page;
use App\Models\User;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\CommonLinks;
use App\Presenters\Exports\Formats\Formats;
use App\Support\PortableText;

it('serves a page as markdown at its slug plus an extension', function () {
    $page = Page::factory()->create([
        'slug' => 'colophon', 'title' => 'Colophon', 'status' => 'published',
        'content' => PortableText::fromPlainText('How this site is built.'),
    ]);

    $this->get('/colophon.md')
        ->assertOk()
        ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
        ->assertSee('How this site is built.');
});

it('404s a page extension for a page that does not exist', function () {
    $this->get('/nothing-here.json')->assertNotFound();
});

it('publishes a page with no fields, only its title, excerpt and body', function () {
    $page = Page::factory()->create([
        'title' => 'Colophon', 'excerpt' => 'How this site is built.',
        'content' => PortableText::fromPlainText('The details.'), 'status' => 'published',
    ]);

    $export = ExportPresenter::for($page);

    expect($export->type)->toBe('page')
        ->and($export->title)->toBe('Colophon')
        ->and($export->summary)->toBe('How this site is built.')
        ->and($export->fields)->toBe([])
        ->and($export->body)->toBe($page->content);
});

it('offers neither sql, geojson nor ics for a page, since it has no fields or aspects', function () {
    $page = Page::factory()->create(['status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($page)));

    expect($formats)->not->toContain('sql')
        ->not->toContain('geojson')
        ->not->toContain('ics');
});

it('resolves CommonLinks for a page without throwing, since a page is not Timelineable', function () {
    $page = Page::factory()->create(['status' => 'published']);

    expect(CommonLinks::for($page))->toBe([]);
});

it('publishes only the header for a private page the request has not unlocked', function () {
    $page = Page::factory()->create([
        'title' => 'Secret', 'status' => 'private', 'password' => 'hunter2',
    ]);

    $response = $this->get('/'.$page->slug.'.json');

    $response->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonMissingPath('fields')
        ->assertJsonMissingPath('links')
        ->assertDontSee('hunter2');
});

it('keeps a private page export out of shared caches', function () {
    $page = Page::factory()->create(['status' => 'private', 'password' => 'hunter2']);

    $this->get('/'.$page->slug.'.json')->assertHeader('Cache-Control', 'no-store, private');
});

it('404s an unpublished page for a guest and serves it to its owner', function () {
    $draft = Page::factory()->create(['slug' => 'draft-page', 'status' => 'draft']);

    $this->get('/draft-page.json')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get('/draft-page.json')
        ->assertOk();
});
