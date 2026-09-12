<?php

namespace App\Presenters;

use App\Data\CardData;
use App\Datasets\Datasets;
use App\Models\Concerns\Timelineable;
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
     * The per-type presenter for a model, declared on its dataset. Each card
     * exposes present() and title(); a caller wanting only the heading
     * (PhotoCaption) takes the latter. Not typed to an interface: every card
     * narrows its parameter to its own model.
     */
    public static function card(Timelineable $model): object
    {
        return Datasets::forModel($model)?->card()
            ?? throw new LogicException('No card presenter registered for '.$model::class);
    }
}
