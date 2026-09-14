<?php

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Models\Film;
use App\Models\ThisWeekWith;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;

it('publishes the summary, flattened to one line', function () {
    $activity = Activity::factory()->create(['description' => "Watched the eclipse\n\nwhile playing"]);

    expect(EntryDescription::for($activity, CardPresenter::for($activity)))->toBe('Watched the eclipse while playing');
});

it('writes the standalone sentence, rating as 8/10, when there is no summary', function () {
    $film = Film::factory()->create(['title' => 'Fall 2: Deadpoint', 'rating' => 8, 'overview' => null]);

    expect(EntryDescription::for($film, CardPresenter::for($film)))->toBe('I watched Fall 2: Deadpoint and rated it 8/10.');
});

it('cuts a long description on a word at 200 characters', function () {
    $activity = Activity::factory()->create(['description' => str_repeat('lovely ', 60)]);

    $description = EntryDescription::for($activity, CardPresenter::for($activity));

    expect(mb_strlen($description))->toBeLessThanOrEqual(201)
        ->and($description)->toEndWith('…');
});

it('falls back to the card title and subtitle when the card has nothing to say', function () {
    $episode = ThisWeekWith::factory()->create(['topic' => null]);
    $card = new CardData(TimelineType::ThisWeekWith, 'Episode 12', null, 'A subtitle', null, null, null, CardMeta::empty());

    expect(EntryDescription::for($episode, $card))->toBe('Episode 12: A subtitle');
});

it('says nothing about a private entry that is not an article', function () {
    $film = Film::factory()->create(['status' => 'private', 'password' => 'hunter2', 'overview' => 'Secret']);

    expect(EntryDescription::for($film, CardPresenter::for($film)))->toBeNull();
});
