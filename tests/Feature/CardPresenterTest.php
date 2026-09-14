<?php

use App\Data\CardData;
use App\Datasets\Datasets;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Book;
use App\Models\Concerns\Timelineable;
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
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;

dataset('timelineable models', [
    'activity' => fn () => Activity::factory()->create(),
    'sleep' => fn () => Sleep::factory()->create(),
    'food' => fn () => Food::factory()->create(),
    'film' => fn () => Film::factory()->create(),
    'tv-episode' => fn () => TvEpisode::factory()->create(),
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

/**
 * Proves CardPresenter::for() is a total resolver: every Timelineable model
 * dispatches to a presenter and returns a CardData, so a model can never
 * silently fall through to the "no presenter registered" branch.
 */
it('resolves a CardData for every Timelineable model', function (Timelineable $model) {
    expect(CardPresenter::for($model))->toBeInstanceOf(CardData::class);
})->with('timelineable models');

it('has every card write its own description', function (Timelineable $model) {
    expect(CardPresenter::card($model)->description($model))->toBeString();
})->with('timelineable models');

/**
 * Guards against a new dataset registered without a card implementing the
 * present/title/description contract, which the hand-maintained dataset above
 * would not catch since it never sees newly registered datasets.
 */
it('requires present, title and description on every registered card', function () {
    foreach (Datasets::all() as $dataset) {
        $card = $dataset->card();

        expect(method_exists($card, 'present'))->toBeTrue("{$dataset->model()}'s card is missing present()")
            ->and(method_exists($card, 'title'))->toBeTrue("{$dataset->model()}'s card is missing title()")
            ->and(method_exists($card, 'description'))->toBeTrue("{$dataset->model()}'s card is missing description()");
    }
});
