<?php

use App\Models\Activity;
use App\Models\Page;

use function Pest\Laravel\get;

it('serves robots.txt as plain text pointing at the sitemap', function () {
    get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee(url('/sitemap.xml'))
        ->assertSee('Disallow: /drafts');
});

it('indexes one sitemap file per year that has entries', function () {
    Activity::factory()->create(['occurred_at' => '2019-06-01 09:00:00']);
    Activity::factory()->create(['occurred_at' => '2026-08-24 09:00:00']);

    $body = get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();

    expect($body)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($body)->toContain(url('/sitemap/2019.xml'))
        ->and($body)->toContain(url('/sitemap/2026.xml'))
        ->and($body)->toContain(url('/sitemap/pages.xml'))
        ->and($body)->not->toContain('/sitemap/2020.xml');

    expect(simplexml_load_string($body))->not->toBeFalse();
});

it('lists an entry alongside the dated pages that hold it', function () {
    $activity = Activity::factory()->create(['name' => 'Morning Run', 'occurred_at' => '2026-08-24 09:00:00']);

    $body = get('/sitemap/2026.xml')->assertOk()->getContent();

    expect($body)->toContain(url('/2026/08/24/'.$activity->slug()))
        ->and($body)->toContain('<loc>'.url('/2026').'</loc>')
        ->and($body)->toContain('<loc>'.url('/2026/08').'</loc>')
        ->and($body)->toContain('<loc>'.url('/2026/08/24').'</loc>');

    expect(simplexml_load_string($body))->not->toBeFalse();
});

it('404s a year with nothing in it', function () {
    Activity::factory()->create(['occurred_at' => '2026-08-24 09:00:00']);

    get('/sitemap/1999.xml')->assertNotFound();
});

it('lists archives and published pages but not drafts', function () {
    Page::factory()->create(['slug' => 'about', 'published' => true]);
    Page::factory()->create(['slug' => 'secret-draft', 'published' => false]);

    $body = get('/sitemap/pages.xml')->assertOk()->getContent();

    // Trailing slash kept deliberately: it is what the canonical tag on the
    // home page emits, and the two must name the same URL.
    expect($body)->toContain('<loc>'.rtrim(url('/'), '/').'/</loc>')
        ->and($body)->toContain(url('/activities'))
        ->and($body)->toContain(url('/stats/activities'))
        ->and($body)->toContain(url('/about'))
        ->and($body)->not->toContain('secret-draft');
});
