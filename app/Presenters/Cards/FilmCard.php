<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\SubtitleToken;
use App\Enums\TimelineType;
use App\Models\Film;
use App\Presenters\SubtitleText;

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
            subtitle: SubtitleText::for($this->tokens($model)),
            subtitleTokens: $this->tokens($model),
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::backdrop($model->optimisedUrl('backdrop')),
        );
    }

    /**
     * What was watched, then how long it ran as its own sentence.
     *
     * @return list<SubtitleToken>
     */
    private function tokens(Film $model): array
    {
        $rated = $model->rating ? " and rated it {$model->rating}/10" : '';
        $tokens = [SubtitleToken::text("I watched {$this->filmClause($model)}{$rated}.")];

        if ($model->meta->runtime) {
            $tokens[] = SubtitleToken::text('It was', ' ');
            $tokens[] = SubtitleToken::dur($model->meta->runtime * 60, ' ', 'minutes');
            $tokens[] = SubtitleToken::text('long.', ' ');
        }

        return $tokens;
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

    public function title(Film $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::Film;
    }
}
