<?php

namespace App\Queries\Lookups;

use App\Exceptions\HardcoverException;
use App\Services\Hardcover\Client;
use Illuminate\Support\Str;

/**
 * Books from Hardcover, filled from the default edition. Hardcover's book-level
 * subtitle is crowd-edited and often from another language, so it is not used.
 * This is the only place Hardcover's shapes are known.
 */
final class BookLookup
{
    private const MAX_RESULTS = 10;

    /** Genres below this many votes are mostly one reader's noise. */
    private const MIN_GENRE_VOTES = 2;

    /** Matches the Textarea field's validation limit. */
    private const MAX_OVERVIEW = 5000;

    public function __construct(private Client $hardcover) {}

    /**
     * @return list<array{value: string, label: string, detail: string|null, fill: array<string, mixed>}>
     */
    public function __invoke(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        try {
            $documents = array_slice($this->hardcover->searchDocuments($query), 0, self::MAX_RESULTS);
        } catch (HardcoverException) {
            return [];
        }

        try {
            $details = $this->hardcover->booksWithEditions(
                array_values(array_filter(array_map(fn (array $document): int => (int) ($document['id'] ?? 0), $documents))),
            );
        } catch (HardcoverException) {
            $details = [];
        }

        return array_values(array_map(
            fn (array $document): array => $this->option($document, $details[(int) ($document['id'] ?? 0)] ?? []),
            $documents,
        ));
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $details
     * @return array{value: string, label: string, detail: string|null, fill: array<string, mixed>}
     */
    private function option(array $document, array $details): array
    {
        $edition = $details['default_physical_edition'] ?? $details['default_ebook_edition'] ?? [];
        $author = implode(', ', (array) data_get($document, 'author_names', [])) ?: data_get($document, 'contributions.0.author.name');
        $cover = data_get($edition, 'image.url') ?? data_get($details, 'image.url') ?? data_get($document, 'image.url');
        $overview = Str::limit(trim((string) (data_get($details, 'description') ?? data_get($document, 'description'))), self::MAX_OVERVIEW, '');

        return [
            'value' => (string) data_get($document, 'title', ''),
            'label' => (string) data_get($document, 'title', 'Untitled'),
            'detail' => $author ?: null,
            'fill' => array_filter([
                'title' => data_get($document, 'title'),
                'meta.author' => $author,
                'meta.subtitle' => data_get($edition, 'subtitle'),
                'meta.isbn' => data_get($edition, 'isbn_13'),
                'pages' => data_get($edition, 'pages') ?? data_get($document, 'pages'),
                'overview' => $overview,
                'cover' => $cover === null ? null : [['id' => 'url:'.$cover, 'name' => 'Cover', 'url' => $cover]],
                'tags' => $this->genres($details),
            ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private function genres(array $details): array
    {
        return collect((array) data_get($details, 'cached_tags.Genre', []))
            ->filter(fn (mixed $genre): bool => is_array($genre)
                && is_string($genre['tag'] ?? null)
                && ($genre['count'] ?? 0) >= self::MIN_GENRE_VOTES)
            ->pluck('tag')
            ->unique()
            ->values()
            ->all();
    }
}
