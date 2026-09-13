<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Book;

/**
 * Builds the timeline card for a book: rating and its author as the subtitle.
 */
final class BookCard
{
    public function present(Book $model): CardData
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

    private function sentence(Book $model): ?string
    {
        $rated = $model->rating ? " and rated it {$model->rating}/10" : '';
        $what = 'this book'.($model->meta->author ? " by {$model->meta->author}" : '');

        return "I read {$what}{$rated}.";
    }

    public function title(Book $model): string
    {
        return $model->title;
    }

    public function type(): TimelineType
    {
        return TimelineType::Book;
    }
}
