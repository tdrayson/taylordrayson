<?php

use App\Actions\Appearances\CreateAppearance;
use App\Actions\Books\CreateBook;
use App\Actions\Books\UpdateBook;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\UpdateEvent;
use App\Actions\Fuel\CreateFuel;
use App\Enums\MediaType;
use App\Models\Media;

it('creates an event with its category as a tag, not a column', function () {
    $event = app(CreateEvent::class)([
        'name' => 'No Such Thing As A Fish',
        'occurred_at' => '2026-08-01 19:30:00',
        'venue_name' => 'Alexandra Palace',
        'tags' => ['Comedy'],
    ]);

    expect($event->tags->pluck('slug')->all())->toBe(['comedy'])
        ->and($event->timelineEntry)->not->toBeNull();
});

it('replaces an event category rather than accumulating tags', function () {
    $event = app(CreateEvent::class)(['name' => 'A gig', 'tags' => ['Theatre']]);

    app(UpdateEvent::class)($event, ['tags' => ['Comedy']]);

    expect($event->fresh()->tags->pluck('slug')->all())->toBe(['comedy']);
});

it('derives price per litre from the two figures on the receipt', function () {
    $fuel = app(CreateFuel::class)([
        'litres' => 33.15,
        'cost' => 45.06,
        'station_name' => 'Shell Caterham',
    ]);

    // 3dp, the documented exception to 2dp money.
    expect($fuel->price_per_litre)->toEqual(1.359);
});

it('keeps a stated price per litre over the derived one', function () {
    $fuel = app(CreateFuel::class)(['litres' => 10.0, 'cost' => 15.0, 'price_per_litre' => 1.499]);

    expect($fuel->price_per_litre)->toEqual(1.499);
});

it('survives a zero-litre fill-up without dividing by zero', function () {
    $fuel = app(CreateFuel::class)(['litres' => 0, 'cost' => 0]);

    expect($fuel->price_per_litre)->toBeNull();
});

it('creates an appearance as a podcast unless told otherwise', function () {
    $appearance = app(CreateAppearance::class)([
        'title' => 'On building in public',
        'show_name' => 'Some Show',
    ]);

    expect($appearance->type)->toBe('podcast')
        ->and($appearance->timelineEntry)->not->toBeNull();
});

it('creates a book as a media row of type book', function () {
    $book = app(CreateBook::class)([
        'title' => 'The Silence of the Girls',
        'meta' => ['author' => 'Pat Barker'],
    ]);

    expect($book->type)->toBe(MediaType::Book)
        ->and($book->meta->author)->toBe('Pat Barker')
        ->and(Media::count())->toBe(1);
});

it('merges book meta on update rather than replacing it', function () {
    $book = app(CreateBook::class)([
        'title' => 'A book',
        'meta' => ['author' => 'Someone', 'isbn' => '9780241983201'],
    ]);

    app(UpdateBook::class)($book, ['meta' => ['author' => 'Someone Else']]);

    // Editing the author must not drop the ISBN alongside it. Asserted on
    // the stored shape as well as the typed one, because `isbn` is a key
    // MediaMeta does not name: it survives only if the DTO round-trips the
    // keys it does not recognise.
    expect($book->fresh()->meta->author)->toBe('Someone Else')
        ->and($book->fresh()->meta->toArray())->toMatchArray([
            'author' => 'Someone Else',
            'isbn' => '9780241983201',
        ]);
});

it('assigns the active car when a fill-up does not name one', function () {
    // Vehicles are config, not a table, and there is effectively one car.
    $fuel = app(CreateFuel::class)(['litres' => 30.0, 'cost' => 42.0]);

    expect($fuel->vehicle_id)->toBe('hn14wxp')
        ->and($fuel->vehicle['model'])->toBe('Aygo');
});
