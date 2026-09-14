<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Film;
use App\Support\Text;

/**
 * Builds the timeline card for a film: a watched-and-rated sentence and the overview as its summary.
 */
final class FilmCard
{
    public function present(Film $model): CardData
    {
        return new CardData(
            type: $this->type(),
            title: $model->title,
            titleLabel: null,
            subtitle: $this->sentence($model),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::backdrop($model->optimisedUrl('backdrop')),
            summary: Text::prose($model->overview),
        );
    }

    /** The overview, else the sentence with the film named: "I watched Fall 2: Deadpoint and rated it 8/10." */
    public function description(Film $model): string
    {
        return Text::prose($model->overview) ?? "I watched {$model->title}{$this->rated($model)}.";
    }

    /** "I watched this 2026 film and rated it 8/10." Genre and runtime stay on the entry page. */
    private function sentence(Film $model): string
    {
        $film = $model->meta->year === null ? 'this film' : "this {$model->meta->year} film";

        return "I watched {$film}{$this->rated($model)}.";
    }

    private function rated(Film $model): string
    {
        return $model->rating ? " and rated it {$model->rating}/10" : '';
    }

    public function title(Film $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::Film;
    }
}
