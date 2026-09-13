<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Subject;
use App\Models\User;
use App\Queries\SubjectCompanions;
use App\Queries\SubjectStats;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists a subject\'s entries, including ones reached through a photograph', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);

    $direct = Activity::factory()->create(['occurred_at' => '2026-03-01 09:00:00']);
    $direct->subjects()->attach($subject);

    $viaPhoto = Activity::factory()->create(['occurred_at' => '2026-03-02 09:00:00']);
    $attachment = $viaPhoto->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 20, 'y' => 30]);

    get('/life/people/clare')->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 2));
});

it('hides an unpublished article from a guest', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    Article::factory()->create(['published' => false])->subjects()->attach($subject);

    get('/life/people/clare')->assertInertia(fn ($page) => $page->has('groups', 0));
});

it('previews an unpublished, subject-tagged article for a signed-in request', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    Article::factory()->create(['published' => false])->subjects()->attach($subject);

    get('/life/people/clare')->assertInertia(fn ($page) => $page->has('groups', 0));

    actingAs(User::factory()->create())
        ->get('/life/people/clare')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 1));
});

it('hides an unpublished article\'s tagged photograph from a guest', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    $article = Article::factory()->create(['published' => false]);
    $attachment = $article->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);

    get('/life/people/clare')->assertInertia(fn ($page) => $page->has('photos', 0));

    actingAs(User::factory()->create())
        ->get('/life/people/clare')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('photos', 1));
});

it('404s on an unknown kind and an unknown slug', function () {
    get('/life/aliens')->assertNotFound();
    get('/life/people/nobody')->assertNotFound();
});

it('does not let a person\'s slug resolve under the wrong kind', function () {
    Subject::factory()->person()->create(['slug' => 'bella']);

    get('/life/pets/bella')->assertNotFound();
});

it('files a spot companion under its own heading, apart from the people', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    $person = Subject::factory()->person()->create(['name' => 'Chloe']);
    $spot = Subject::factory()->create(['kind' => 'spot', 'name' => 'Cocoa Nut']);

    $shared = Activity::factory()->create(['occurred_at' => '2026-04-01 09:00:00']);
    $shared->subjects()->attach([$subject->id, $person->id, $spot->id]);

    $grouped = app(SubjectCompanions::class)->grouped($subject);

    expect($grouped->keys()->all())->toBe(['Appears with', 'Also at'])
        ->and($grouped['Appears with']->pluck('name')->all())->toBe(['Chloe'])
        ->and($grouped['Also at']->pluck('name')->all())->toBe(['Cocoa Nut']);
});

it('ranks a companion by entries shared, not by how many photographs they are tagged in', function () {
    $subject = Subject::factory()->person()->create();
    $oneOuting = Subject::factory()->person()->create(['name' => 'Three photos, one day']);
    $twoOutings = Subject::factory()->person()->create(['name' => 'Two separate days']);

    // Three tags on a single entry: one shared entry, however many frames.
    $busy = Activity::factory()->create(['occurred_at' => '2026-04-01 09:00:00']);
    $busy->subjects()->attach($subject);

    foreach (range(1, 3) as $frame) {
        $attachment = $busy->addMediaFromString(fakeJpeg())->usingFileName("p{$frame}.jpg")->toMediaCollection('photos');
        $attachment->subjects()->attach($oneOuting, ['role' => 'subject', 'x' => 10, 'y' => 10]);
    }

    foreach (['2026-04-02 09:00:00', '2026-04-03 09:00:00'] as $when) {
        $entry = Activity::factory()->create(['occurred_at' => $when]);
        $entry->subjects()->attach([$subject->id, $twoOutings->id]);
    }

    expect(app(SubjectCompanions::class)($subject)->pluck('name')->all())
        ->toBe(['Two separate days', 'Three photos, one day']);
});

it('counts a companion tagged only through a shared photograph, but not a subject on an unrelated entry', function () {
    $subject = Subject::factory()->person()->create();
    $companion = Subject::factory()->person()->create();
    $stranger = Subject::factory()->person()->create();

    $shared = Activity::factory()->create(['occurred_at' => '2026-04-01 09:00:00']);
    $attachment = $shared->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);
    $attachment->subjects()->attach($companion, ['role' => 'subject', 'x' => 20, 'y' => 20]);

    Activity::factory()->create(['occurred_at' => '2026-04-02 09:00:00'])->subjects()->attach($stranger);

    $companions = app(SubjectCompanions::class)($subject);

    expect($companions->pluck('id')->all())->toBe([$companion->id]);
});

it('reports the photo count and the first-to-last span', function () {
    $subject = Subject::factory()->person()->create();

    $first = Activity::factory()->create(['occurred_at' => '2026-01-01 09:00:00']);
    $first->subjects()->attach($subject);

    $last = Activity::factory()->create(['occurred_at' => '2026-01-15 09:00:00']);
    $attachment = $last->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);

    $stats = app(SubjectStats::class)($subject);

    expect($stats['photos'])->toBe(1)
        ->and($stats['first'])->toBe('2026-01-01')
        ->and($stats['last'])->toBe('2026-01-15');
});

it('pages a subject\'s timeline, keeping the photos whole on every page', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);

    // 26 separate days, one over the 25-per-page cut.
    foreach (range(1, 26) as $day) {
        Activity::factory()
            ->create(['occurred_at' => sprintf('2026-03-%02d 09:00:00', $day)])
            ->subjects()->attach($subject);
    }

    get('/life/people/clare')->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 25)->where('lastPage', 2)->where('currentPage', 1));

    get('/life/people/clare?page=2')->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 1)->where('currentPage', 2));

    // Out of range clamps rather than 404s or renders an empty page.
    get('/life/people/clare?page=99')->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentPage', 2));
});

it('sends the whole kind for the browser to filter, with the facet preselected', function () {
    Subject::factory()->spot()->create(['name' => 'Coco', 'category' => 'cafe']);
    Subject::factory()->spot()->create(['name' => 'Beeches', 'category' => 'trail']);

    // Every subject travels regardless of the facet: the filtering is client
    // side, so a narrowed payload would leave nothing to animate back in.
    get('/life/spots?category=cafe')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subjects', 2)
            ->where('category', 'cafe')
            ->where('subjects.1.categoryValue', 'cafe'));

    // A category nothing uses is ignored rather than erroring.
    get('/life/spots?category=nowhere')->assertOk()
        ->assertInertia(fn ($page) => $page->where('category', null)->has('subjects', 2));
});

it('folds the counts into the facts rows, with distance in metres', function () {
    $subject = Subject::factory()->thing()->create(['slug' => 'allez']);

    Activity::factory()->create(['type' => 'ride', 'distance' => 20000, 'occurred_at' => '2026-01-05 09:00:00'])
        ->subjects()->attach($subject);
    Activity::factory()->create(['type' => 'ride', 'distance' => 10000, 'occurred_at' => '2026-03-05 09:00:00'])
        ->subjects()->attach($subject);

    $rows = collect(app(SubjectStats::class)($subject)['rows'])->keyBy('label');

    // Raw metres, not a formatted string: the row resolves through the
    // visitor's own mi/km setting in the browser.
    expect($rows['Distance']['distanceM'])->toBe(30000)
        ->and($rows['Activities']['value'])->toBe('2')
        ->and($rows['First appearance']['value'])->toBe('January 2026')
        ->and($rows['Latest appearance']['value'])->toBe('March 2026');
});

it('leaves out a distance row for a subject with no activities', function () {
    $subject = Subject::factory()->person()->create();
    Checkin::factory()->create(['occurred_at' => '2026-02-02 09:00:00'])->subjects()->attach($subject);

    $labels = collect(app(SubjectStats::class)($subject)['rows'])->pluck('label');

    expect($labels)->not->toContain('Distance')
        ->and($labels)->toContain('Places');
});
