<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Media;
use App\Support\ShowTitle;

/**
 * Builds the timeline card for a Media entry: rating and a type-specific detail
 * (film year, TV season/episode, book author) as the subtitle. An episode is
 * titled by the episode, since a day's worth of one show would otherwise repeat
 * the same title down the feed; its show leads the subtitle instead.
 */
final class MediaCard
{
    public function present(Media $model): CardData
    {
        $show = ShowTitle::for($model);

        // Falls back to the show when the episode itself is unnamed, so the card
        // is never headed by nothing.
        $title = $model->title !== '' ? $model->title : ($show ?? '');

        return new CardData(
            type: TimelineType::Media,
            icon: 'film',
            title: $title,
            titleLabel: null,
            subtitle: $this->sentence($model, $show, $title),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'media',
            range: null,
            meta: CardMeta::empty(),
        );
    }

    /**
     * What was watched or read, as a sentence. An episode names its show here
     * because the card title is the episode alone and the eyebrow only says
     * "Media", so nothing else on the card identifies the programme.
     */
    private function sentence(Media $model, ?string $show, string $title): ?string
    {
        $rated = $model->rating ? " and rated it {$model->rating}/10" : '';

        $what = match ($model->type) {
            MediaType::Film => $this->filmClause($model),
            MediaType::TvEpisode => $this->episodeClause($model, $show, $title),
            MediaType::Book => 'this book'.($model->meta->author ? " by {$model->meta->author}" : ''),
            default => null,
        };

        if ($what === null) {
            return $model->rating ? "I rated this {$model->rating}/10." : null;
        }

        $verb = $model->type === MediaType::Book ? 'read' : 'watched';

        return "I {$verb} {$what}{$rated}.";
    }

    /** "this 2024 film", or just "this film" when the year is unknown. */
    private function filmClause(Media $model): string
    {
        return $model->meta->year === null ? 'this film' : "this {$model->meta->year} film";
    }

    /**
     * The show and where in it, spelled out rather than as "S04E04": the
     * sentence is also the meta description and the feed summary.
     */
    private function episodeClause(Media $model, ?string $show, string $title): ?string
    {
        // The show is already the heading when the episode had no name of its
        // own, so repeating it would print the same words twice.
        $named = $show !== null && $show !== $title ? $show : null;

        $where = $model->meta->season !== null && $model->meta->episode !== null
            ? sprintf('season %d episode %d', $model->meta->season, $model->meta->episode)
            : null;

        return match (true) {
            $named !== null && $where !== null => "{$named}, {$where}",
            $named !== null => $named,
            $where !== null => "this one, {$where}",
            default => null,
        };
    }
}
