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
        return self::card($model)->present($model);
    }

    /**
     * The per-type presenter for a model, so this match stays the only place
     * types are registered. Each card exposes present() and title(); a caller
     * wanting only the heading (PhotoCaption) takes the latter and skips the
     * cost of the rest. Not typed to an interface: every card narrows its
     * parameter to its own model, which an interface would have to widen.
     */
    public static function card(Timelineable $model): object
    {
        return match (true) {
            $model instanceof Activity => new ActivityCard,
            $model instanceof Sleep => new SleepCard,
            $model instanceof Calorie => new CalorieCard,
            $model instanceof Media => new MediaCard,
            $model instanceof Event => new EventCard,
            $model instanceof Appearance => new AppearanceCard,
            $model instanceof Podcast => new PodcastCard,
            $model instanceof Flight => new FlightCard,
            $model instanceof Checkin => new CheckinCard,
            $model instanceof Fuel => new FuelCard,
            $model instanceof Project => new ProjectCard,
            $model instanceof Article => new ArticleCard,
            $model instanceof Note => new NoteCard,
            default => throw new LogicException('No card presenter registered for '.$model::class),
        };
    }
}
