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

    /** The overview, else the sentence with the book named: "I read Project Hail Mary by Andy Weir and rated it 9/10." */
    public function description(Book $model): string
    {
        $author = $model->meta->author ? " by {$model->meta->author}" : '';

        return Text::prose($model->overview) ?? "I read {$model->title}{$author}{$this->rated($model)}.";
    }

    /** "I read Andy Weir's book and rated it 9/10." */
    private function sentence(Book $model): string
    {
        $book = $model->meta->author ? "{$model->meta->author}'s book" : 'this book';

        return "I read {$book}{$this->rated($model)}.";
    }

    private function rated(Book $model): string
    {
        return $model->rating ? " and rated it {$model->rating}/10" : '';
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
