<?php

use App\Data\CardData;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Concerns\Timelineable;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use App\Presenters\CardPresenter;

/**
 * Proves CardPresenter::for() is a total resolver: every Timelineable model
 * dispatches to a presenter and returns a CardData, so a model can never
 * silently fall through to the "no presenter registered" branch.
 */
it('resolves a CardData for every timelineable model', function (Timelineable $model) {
    expect(CardPresenter::for($model))->toBeInstanceOf(CardData::class);
})->with([
    'activity' => fn () => Activity::factory()->create(),
    'sleep' => fn () => Sleep::factory()->create(),
    'calorie' => fn () => Calorie::factory()->create(),
    'media' => fn () => Media::factory()->create(),
    'event' => fn () => Event::factory()->create(),
    'appearance' => fn () => Appearance::factory()->create(),
    'podcast' => fn () => Podcast::factory()->create(),
    'flight' => fn () => Flight::factory()->create(),
    'checkin' => fn () => Checkin::factory()->create(),
    'fuel' => fn () => Fuel::factory()->create(),
    'project' => fn () => Project::factory()->create(),
    'article' => fn () => Article::factory()->create(),
    'note' => fn () => Note::factory()->create(),
]);
