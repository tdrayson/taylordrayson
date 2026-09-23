<?php

namespace App\Services\OpenLibrary;

/**
 * Open Library, for the blurb of a specific printing. Hardcover editions carry
 * no description, and its book-level one is sometimes another book's entirely.
 */
class Client
{
    public function __construct(private readonly Connector $connector) {}

    /**
     * The description of the edition with this ISBN, else of the work it belongs to.
     *
     * @return string|null Null when neither has one, or Open Library does not know the ISBN.
     */
    public function description(string $isbn): ?string
    {
        $edition = $this->connector->json('/isbn/'.rawurlencode($isbn).'.json');

        if ($edition === null) {
            return null;
        }

        $work = data_get($edition, 'works.0.key');

        return $this->text($edition['description'] ?? null)
            ?? (is_string($work) ? $this->text(data_get($this->connector->json($work.'.json'), 'description')) : null);
    }

    /**
     * Open Library stores a description either as a string or as `{type, value}`.
     */
    private function text(mixed $description): ?string
    {
        $text = trim((string) (is_array($description) ? ($description['value'] ?? '') : $description));

        return $text === '' ? null : $text;
    }
}
