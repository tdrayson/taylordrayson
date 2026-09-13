<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Book;
use App\Presenters\Cards\BookCard;

/**
 * Books logged as read, entered by hand.
 */
final class BookDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Book;
    }

    public function model(): string
    {
        return Book::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Watching;
    }

    public function icon(): string
    {
        return 'BookOpen01Icon';
    }

    public function label(): string
    {
        return 'Book';
    }

    public function plural(): string
    {
        return 'Books';
    }

    public function slug(): string
    {
        return 'books';
    }

    public function keywords(): string
    {
        return 'read book reading';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['read', 'read'];
    }

    public function card(): BookCard
    {
        return new BookCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Book'],
            'rating' => ['label' => 'Rating', 'dataType' => 'number', 'column' => 'rating', 'category' => 'Book'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title'];
    }
}
