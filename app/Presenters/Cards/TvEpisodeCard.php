<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\TvEpisode;
use App\Support\ShowTitle;
use App\Support\Text;

/**
 * Builds the timeline card for an episode: rating and the show/place-in-run as
 * the subtitle. Titled by the episode, since a day's worth of one show would
 * otherwise repeat the same title down the feed; its show leads the subtitle
 * instead.
 */
final class TvEpisodeCard
{
    public function present(TvEpisode $model): CardData
    {
        $show = ShowTitle::for($model);
        $title = $this->title($model);

        return new CardData(
            type: $this->type(),
            title: $title,
            titleLabel: null,
            subtitle: $this->sentence($model, $show, $title),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::backdrop($this->backdrop($model)),
            summary: Text::prose($model->overview),
        );
    }

    /** The overview, else the episode named with its show: "I watched season 4 episode 6 of Ted Lasso and rated it 9/10." */
    public function description(TvEpisode $model): string
    {
        return Text::prose($model->overview) ?? "I watched {$this->subject($model)}{$this->rated($model)}.";
    }

    /** The episode named for a reader with no heading above it, also the object of the OG headline. */
    public function subject(TvEpisode $model): string
    {
        $show = ShowTitle::for($model);
        $where = $this->where($model);

        return match (true) {
            $show !== null && $where !== null => "{$where} of {$show}",
            $show !== null => "an episode of {$show}",
            $model->title !== '' => $model->title,
            default => $where ?? 'an episode',
        };
    }

    /**
     * The wide artwork behind the card. An episode has none of its own, so it
     * reads its show's, which is what EntryArtwork already does for the entry
     * page; only the backdrop is wanted here.
     */
    private function backdrop(TvEpisode $model): ?string
    {
        $source = $model->optimisedUrl('backdrop') === null
            ? $model->tvShow ?? $model
            : $model;

        return $source->optimisedUrl('backdrop');
    }

    private function sentence(TvEpisode $model, ?string $show, string $title): ?string
    {
        $what = $this->episodeClause($model, $show, $title);

        if ($what === null) {
            return $model->rating ? "I rated this {$model->rating}/10." : null;
        }

        return "I watched {$what}{$this->rated($model)}.";
    }

    /** The show and where in it, for the subtitle under the episode's own title. */
    private function episodeClause(TvEpisode $model, ?string $show, string $title): ?string
    {
        // The show is already the heading when the episode had no name of its
        // own, so repeating it would print the same words twice.
        $named = $show !== null && $show !== $title ? $show : null;
        $where = $this->where($model);

        return match (true) {
            $named !== null && $where !== null => "{$where} of {$named}",
            $named !== null => $named,
            $where !== null => $where,
            default => null,
        };
    }

    /** "season 4 episode 6", spelled out rather than "S04E06"; null without both numbers. */
    private function where(TvEpisode $model): ?string
    {
        return $model->meta->season !== null && $model->meta->episode !== null
            ? sprintf('season %d episode %d', $model->meta->season, $model->meta->episode)
            : null;
    }

    private function rated(TvEpisode $model): string
    {
        return $model->rating ? " and rated it {$model->rating}/10" : '';
    }

    /**
     * Falls back to the show when the episode itself is unnamed, so the card is
     * never headed by nothing.
     */
    public function title(TvEpisode $model): string
    {
        return $model->title !== '' ? $model->title : (ShowTitle::for($model) ?? '');
    }

    public function type(): TimelineType
    {
        return TimelineType::TvEpisode;
    }
}
