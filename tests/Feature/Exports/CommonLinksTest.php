<?php

use App\Data\ExportLink;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Trip;
use App\Presenters\Exports\CommonLinks;

/** The single link of a given key, or null. */
function commonLink(array $links, string $key): ?ExportLink
{
    return collect($links)->first(fn (ExportLink $link): bool => $link->key === $key);
}

it('links every entry to its type archive and its day', function () {
    $links = CommonLinks::for(krkToLgw());

    expect(commonLink($links, 'type')?->url)->toBe(config('app.url').'/flights')
        ->and(commonLink($links, 'day'))->not->toBeNull();
});

it('links a tagged entry to each of its tags, with a category rel', function () {
    $note = Note::factory()->create();
    $note->syncTagNames(['Poland', 'Flights']);

    $links = CommonLinks::for($note->fresh());
    $tagLinks = collect($links)->filter(fn (ExportLink $link): bool => $link->key === 'tag')->values();

    expect($tagLinks)->toHaveCount(2)
        ->and($tagLinks->pluck('rel')->all())->toBe(['category', 'category'])
        ->and($tagLinks->pluck('title')->all())->toContain('Poland', 'Flights');
});

it('adds no tag links for a model with none', function () {
    $links = CommonLinks::for(krkToLgw());

    expect(commonLink($links, 'tag'))->toBeNull();
});

it('links an entry to the trip its date falls inside', function () {
    $trip = Trip::factory()->create([
        'starts_at' => '2026-06-01 08:00:00',
        'ends_at' => '2026-06-10 22:00:00',
        'timezone' => 'Europe/London',
    ]);
    $note = Note::factory()->create(['occurred_at' => '2026-06-05 12:00:00', 'timezone' => 'Europe/London']);

    $link = commonLink(CommonLinks::for($note), 'trip');

    expect($link)->not->toBeNull()
        ->and($link->title)->toBe($trip->title);
});

it('adds no trip link for an entry outside every trip window', function () {
    Trip::factory()->create([
        'starts_at' => '2026-01-01 08:00:00',
        'ends_at' => '2026-01-10 22:00:00',
        'timezone' => 'Europe/London',
    ]);
    $note = Note::factory()->create(['occurred_at' => '2026-06-05 12:00:00', 'timezone' => 'Europe/London']);

    expect(commonLink(CommonLinks::for($note), 'trip'))->toBeNull();
});

it('links a synced entry back to its source, with a syndication rel', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '999']);

    $link = commonLink(CommonLinks::for($activity), 'source');

    expect($link)->not->toBeNull()
        ->and($link->rel)->toBe('syndication')
        ->and($link->url)->toBe('https://www.strava.com/activities/999');
});

it('adds no source link for a model with none', function () {
    expect(commonLink(CommonLinks::for(krkToLgw()), 'source'))->toBeNull();
});
