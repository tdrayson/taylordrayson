<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Book;
use App\Support\Text;

/**
 * Builds the timeline card for a book: who wrote it, my rating, and the overview as its summary.
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
            summary: Text::prose($model->overview),
        );
    }

    /** "I read Andy Weir's book and rated it 9/10." */
    private function sentence(Book $model): string
    {
        $book = $model->meta->author ? "{$model->meta->author}'s book" : 'this book';
        $rated = $model->rating ? " and rated it {$model->rating}/10" : '';

        return "I read {$book}{$rated}.";
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
