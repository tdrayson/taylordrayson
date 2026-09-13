<?php

use App\Models\Article;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\User;

it('draws every primary field as an input, not a chip', function () {
    $this->actingAs(User::factory()->create());

    // Asserting the rendered DOM, not the Inertia props: a chip would satisfy
    // a text assertion while the field was still collapsed.
    visit('/new/fuel')
        ->assertPresent('#cost')
        ->assertPresent('#price_per_litre')
        ->assertPresent('#occurred_at')
        ->assertPresent('#vehicle_id')
        ->assertNoJavascriptErrors();
});

it('draws a short form whole, with nothing left behind the add menu', function () {
    $this->actingAs(User::factory()->create());

    // A note offers few enough fields that every one is shown, including the
    // optionals that a longer type would keep behind "+ Add field".
    visit('/new/note')
        ->assertPresent('#occurred_at')
        ->assertPresent('#tags')
        ->assertPresent('#slug')
        ->assertDontSee('Add field')
        ->assertNoJavascriptErrors();
});

it('keeps the timezone inside the date it qualifies, not beside it', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/new/note');

    $page->assertDontSee('Timezone')
        ->click('#occurred_at')
        ->assertSee('Timezone')
        ->assertNoJavascriptErrors();
});

it('sets the publish date by free text and the time by hand, and saves both', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'A test note.', 20);
    $page->fill('#slug', 'a-test-note');

    $page->click('#occurred_at')
        ->fill('[placeholder="yesterday 9am, 3 Aug 18:30"]', '5 January 2027')
        ->keys('[placeholder="yesterday 9am, 3 Aug 18:30"]', 'Enter')
        ->click('#occurred_at')
        ->fill('input[type="time"]', '17:29')
        ->click('button:has-text("Done")')
        ->click('button:has-text("Post")')
        ->assertNoJavascriptErrors();

    expect(Note::first()->occurred_at->format('Y-m-d H:i'))->toBe('2027-01-05 17:29');
});

it('draws even a long form whole', function () {
    $this->actingAs(User::factory()->create());

    // An event offers the most fields of any type, including rarely-set ones
    // that used to sit behind "+ Add field".
    visit('/new/event')
        ->assertDontSee('Add field')
        ->assertPresent('#organiser')
        ->assertPresent('#url')
        ->assertNoJavascriptErrors();
});

it('slugifies the title as it is typed, until the slug is written by hand', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/new/article');

    $page->type('#title', 'A Piece About Coffee')
        ->assertValue('#slug', 'a-piece-about-coffee')
        // Typing a slug is how you say you want that one, so the title stops
        // driving it.
        ->fill('#slug', 'coffee')
        ->type('#title', ' Again')
        ->assertValue('#slug', 'coffee')
        ->assertNoJavascriptErrors();
});

it('keeps a draft slug tracking its title however often it is saved', function () {
    $this->actingAs(User::factory()->create());

    $article = Article::factory()->create(['title' => 'Before', 'slug' => 'before', 'published' => false]);

    visit($article->url().'?edit')
        ->assertMissing('#slug[readonly]')
        ->fill('#title', 'After')
        ->assertValue('#slug', 'after')
        ->assertNoJavascriptErrors();
});

it('settles the slug at publish, and stops it being edited', function () {
    $this->actingAs(User::factory()->create());

    // Published means linkable, so the URL stops moving under whoever has it.
    $article = Article::factory()->create(['title' => 'Before', 'slug' => 'before', 'published' => true]);

    visit($article->url().'?edit')
        ->assertPresent('#slug[readonly]')
        ->fill('#title', 'After')
        ->assertValue('#slug', 'before')
        ->assertNoJavascriptErrors();
});

it('shows a map for a location that has resolved coordinates', function () {
    $this->actingAs(User::factory()->create());

    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-08-13 12:00:00',
        'station_name' => 'Beddington Lane Service Station',
        'latitude' => 51.3835,
        'longitude' => -0.1285,
    ]);

    visit($fuel->url().'?edit')
        ->assertPresent('canvas.maplibregl-canvas')
        ->assertNoJavascriptErrors();
});

it('collapses the address to a summary that opens on demand', function () {
    $this->actingAs(User::factory()->create());

    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-08-13 12:00:00',
        'station_name' => 'Beddington Lane Service Station',
        'address' => '35 Beddington Lane',
        'postcode' => 'CR0 4TJ',
        'city' => 'Croydon',
    ]);

    $page = visit($fuel->url().'?edit');

    // Closed, the address reads as one line and its inputs are not in the DOM.
    $page->assertSee('35 Beddington Lane, CR0 4TJ, Croydon')
        ->assertMissing('#postcode')
        ->click('Edit')
        ->assertPresent('#postcode')
        ->assertNoJavascriptErrors();
});
