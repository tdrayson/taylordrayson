<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Episode;
use App\Support\ShowTitle;

/**
 * Builds the timeline card for an episode: rating and the show/place-in-run as
 * the subtitle. Titled by the episode, since a day's worth of one show would
 * otherwise repeat the same title down the feed; its show leads the subtitle
 * instead.
 */
final class EpisodeCard
{
    public function present(Episode $model): CardData
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
        );
    }

    /**
     * The wide artwork behind the card. An episode has none of its own, so it
     * reads its show's, which is what MediaArtwork already does for the entry
     * page; only the backdrop is wanted here.
     */
    private function backdrop(Episode $model): ?string
    {
        $source = $model->optimisedUrl('backdrop') === null
            ? $model->series ?? $model
            : $model;

        return $source->optimisedUrl('backdrop');
    }

    private function sentence(Episode $model, ?string $show, string $title): ?string
    {
        $rated = $model->rating ? " and rated it {$model->rating}/10" : '';
        $what = $this->episodeClause($model, $show, $title);

        if ($what === null) {
            return $model->rating ? "I rated this {$model->rating}/10." : null;
        }

        return "I watched {$what}{$rated}.";
    }

    /** The show and where in it, spelled out rather than as "S04E04". */
    private function episodeClause(Episode $model, ?string $show, string $title): ?string
    {
        // The show is already the heading when the episode had no name of its
        // own, so repeating it would print the same words twice.
        $named = $show !== null && $show !== $title ? $show : null;

        $where = $model->meta->season !== null && $model->meta->episode !== null
            ? sprintf('season %d episode %d', $model->meta->season, $model->meta->episode)
            : null;

        return match (true) {
            $named !== null && $where !== null => "{$where} of {$named}",
            $named !== null => $named,
            $where !== null => $where,
            default => null,
        };
    }

    /**
     * Falls back to the show when the episode itself is unnamed, so the card is
     * never headed by nothing.
     */
    public function title(Episode $model): string
    {
        return $model->title !== '' ? $model->title : (ShowTitle::for($model) ?? '');
    }

    public function type(): TimelineType
    {
        return TimelineType::Episode;
    }
}
