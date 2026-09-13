<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Film;

/**
 * Builds the timeline card for a film: rating and year/genre as the subtitle.
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
        );
    }

    private function sentence(Film $model): ?string
    {
        $rated = $model->rating ? " and rated it {$model->rating}/10" : '';
        $what = $this->filmClause($model);
        $runtime = $this->runtimeSentence($model);

        return "I watched {$what}{$rated}.{$runtime}";
    }

    /**
     * "this 2024 drama": the year and TMDB's leading genre, which is ordered by
     * relevance. One genre only, since stringing two together reads as neither
     * ("this science fiction and mystery film").
     */
    private function filmClause(Film $model): string
    {
        $genre = $model->meta->tmdb->genres[0] ?? null;
        $what = $genre === null ? 'film' : mb_strtolower($genre).' film';

        return $model->meta->year === null ? "this {$what}" : "this {$model->meta->year} {$what}";
    }

    /** How long a film ran, as its own sentence. */
    private function runtimeSentence(Film $model): string
    {
        $minutes = $model->meta->runtime;

        return $minutes ? " It was {$minutes} minutes long." : '';
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
