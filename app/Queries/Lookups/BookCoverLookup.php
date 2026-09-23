<?php

namespace App\Queries\Lookups;

use App\Data\BookEdition;
use App\Exceptions\HardcoverException;
use App\Services\Hardcover\Client;

/**
 * The editions of the book that best matches a title and author, for the cover dialog.
 */
final class BookCoverLookup
{
    public function __construct(private Client $hardcover) {}

    /**
     * @return list<BookEdition>
     */
    public function __invoke(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        try {
            $document = $this->hardcover->searchDocuments($query)[0] ?? null;
        } catch (HardcoverException) {
            return [];
        }

        if ($document === null) {
            return [];
        }

        $id = (int) ($document['id'] ?? 0);

        try {
            $details = $this->hardcover->booksWithEditions([$id])[$id] ?? [];
        } catch (HardcoverException) {
            $details = [];
        }

        return BookCovers::from($document, $details);
    }
}
