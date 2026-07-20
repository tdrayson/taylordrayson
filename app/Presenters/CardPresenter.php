<?php

namespace App\Presenters;

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
use App\Presenters\Cards\ActivityCard;
use App\Presenters\Cards\AppearanceCard;
use App\Presenters\Cards\ArticleCard;
use App\Presenters\Cards\CalorieCard;
use App\Presenters\Cards\CheckinCard;
use App\Presenters\Cards\EventCard;
use App\Presenters\Cards\FlightCard;
use App\Presenters\Cards\FuelCard;
use App\Presenters\Cards\MediaCard;
use App\Presenters\Cards\NoteCard;
use App\Presenters\Cards\PodcastCard;
use App\Presenters\Cards\ProjectCard;
use App\Presenters\Cards\SleepCard;
use LogicException;

/**
 * Resolves a Timelineable model to its timeline card payload. The single
 * entry point for building a card from any context (controllers, Blade
 * views, actions) — callers no longer call `$model->card()` directly, since
 * card-building lives in a per-type presenter, not on the model.
 */
final class CardPresenter
{
    public static function for(Timelineable $model): CardData
    {
        return match (true) {
            $model instanceof Activity => (new ActivityCard)->present($model),
            $model instanceof Sleep => (new SleepCard)->present($model),
            $model instanceof Calorie => (new CalorieCard)->present($model),
            $model instanceof Media => (new MediaCard)->present($model),
            $model instanceof Event => (new EventCard)->present($model),
            $model instanceof Appearance => (new AppearanceCard)->present($model),
            $model instanceof Podcast => (new PodcastCard)->present($model),
            $model instanceof Flight => (new FlightCard)->present($model),
            $model instanceof Checkin => (new CheckinCard)->present($model),
            $model instanceof Fuel => (new FuelCard)->present($model),
            $model instanceof Project => (new ProjectCard)->present($model),
            $model instanceof Article => (new ArticleCard)->present($model),
            $model instanceof Note => (new NoteCard)->present($model),
            default => throw new LogicException('No card presenter registered for '.$model::class),
        };
    }
}
