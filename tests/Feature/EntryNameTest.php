<?php

use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Sleep;
use App\Presenters\CardPresenter;
use App\Support\EntryName;
use App\Timeline\TypeRegistry;

/**
 * What one of my entries is called when another one points at it.
 */
it('keeps a real title, which is already a name', function () {
    $article = Article::factory()->create(['title' => 'How OG images work']);

    expect(EntryName::for($article))->toBe('How OG images work');
});

// "Replied to at Starbucks" is not a sentence. These types are called by what
// they are and when they happened instead.
it('names a type whose title is a phrase by what it is and when', function () {
    $checkin = Checkin::factory()->create(['occurred_at' => now()->setDate(now()->year, 3, 14)]);

    expect(EntryName::for($checkin))->toBe('a check-in from 14 March')
        ->and(EntryName::for($checkin, possessive: true))->toBe('my check-in from 14 March');
});

// A year only earns its place once it is no longer this one.
it('says the year only when it is not this one', function () {
    $note = Note::factory()->create(['occurred_at' => now()->setDate(now()->year - 2, 3, 14)]);

    expect(EntryName::for($note))->toBe('a note from 14 March '.(now()->year - 2));
});

/**
 * The list in EntryName mirrors a judgment the cards already make: a card sets
 * a titleLabel exactly when its own title does not stand up alone. This holds
 * the two together, so a new type that needs one is caught here rather than by
 * a reader meeting "Replied to £50.64 at Beddington Lane".
 */
it('covers every type whose card says its title needs context', function () {
    $needContext = [];

    foreach (TypeRegistry::all() as $definition) {
        $model = $definition['model']::factory()->create();

        if (CardPresenter::for($model)->titleLabel !== null) {
            $needContext[] = $model::class;
        }
    }

    $unnamed = array_filter(
        [Sleep::class, Calorie::class, Checkin::class, Fuel::class],
        fn (string $class): bool => in_array($class, $needContext, true),
    );

    expect(array_values($unnamed))->toBe($needContext)
        // A note is the fifth, with no title at all rather than one lacking context.
        ->and(EntryName::isUnnamed(Note::factory()->create()))->toBeTrue();
});

// Activities and events carry a name rather than a title, and flights carry
// neither: their card builds a route, which is not a thing you reply "to".
it('finds the name of a type that has one under another column', function () {
    $event = Event::factory()->create(['name' => 'London Squash Classic']);

    expect(EntryName::for($event))->toBe('London Squash Classic');
});

it('falls back to what the archive calls a type with no name at all', function () {
    $flight = Flight::factory()->create(['occurred_at' => now()->setDate(now()->year, 6, 8)]);

    expect(EntryName::for($flight, possessive: true))->toBe('my flight from 8 June');
});
