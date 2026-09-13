<?php

use App\Data\CardData;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Book;
use App\Models\Concerns\Timelineable;
use App\Models\Episode;
use App\Models\Event;
use App\Models\Film;
use App\Models\Flight;
use App\Models\Food;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Place;
use App\Models\Project;
use App\Models\Sleep;
use App\Models\ThisWeekWith;
use App\Presenters\CardPresenter;

/**
 * Proves CardPresenter::for() is a total resolver: every Timelineable model
 * dispatches to a presenter and returns a CardData, so a model can never
 * silently fall through to the "no presenter registered" branch.
 */
it('resolves a CardData for every Timelineable model', function (Timelineable $model) {
    expect(CardPresenter::for($model))->toBeInstanceOf(CardData::class);
})->with([
    'activity' => fn () => Activity::factory()->create(),
    'sleep' => fn () => Sleep::factory()->create(),
    'food' => fn () => Food::factory()->create(),
    'film' => fn () => Film::factory()->create(),
    'episode' => fn () => Episode::factory()->create(),
    'book' => fn () => Book::factory()->create(),
    'event' => fn () => Event::factory()->create(),
    'appearance' => fn () => Appearance::factory()->create(),
    'this-week-with' => fn () => ThisWeekWith::factory()->create(),
    'flight' => fn () => Flight::factory()->create(),
    'place' => fn () => Place::factory()->create(),
    'fuel' => fn () => Fuel::factory()->create(),
    'project' => fn () => Project::factory()->create(),
    'article' => fn () => Article::factory()->create(),
    'note' => fn () => Note::factory()->create(),
]);
