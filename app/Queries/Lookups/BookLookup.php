<?php

namespace App\Queries\Lookups;

use App\Exceptions\HardcoverException;
use App\Services\Hardcover;

/**
 * Books from Hardcover. The client fails closed on a bad response, which would
 * otherwise read as "no matches" and quietly send you off to type it by hand;
 * here a failure returns nothing but is not mistaken for an empty library.
 */
final class BookLookup
{
    public function __construct(private Hardcover $hardcover) {}

    /**
     * @return list<array{value: string, label: string, detail: string|null, fill: array<string, mixed>}>
     */
    public function __invoke(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        try {
            $documents = $this->hardcover->searchDocuments($query);
        } catch (HardcoverException) {
            return [];
        }

        return array_values(array_map(function (array $document): array {
            $author = data_get($document, 'author_names.0') ?? data_get($document, 'contributions.0.author.name');
            $year = data_get($document, 'release_year');

            return [
                'value' => (string) data_get($document, 'title', ''),
                'label' => (string) data_get($document, 'title', 'Untitled'),
                'detail' => trim(implode(', ', array_filter([$author, $year]))) ?: null,
                // Picking a result fills the other fields too, which is the
                // point of the lookup: one choice, not four retyped facts.
                'fill' => array_filter([
                    'title' => data_get($document, 'title'),
                    'meta.author' => $author,
                    'meta.year' => $year,
                    'meta.isbn' => data_get($document, 'isbns.0'),
                ], fn ($value): bool => $value !== null && $value !== ''),
            ];
        }, array_slice($documents, 0, 10)));
    }
}
