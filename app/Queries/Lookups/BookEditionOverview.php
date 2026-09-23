<?php

namespace App\Queries\Lookups;

use App\Services\OpenLibrary\Client;
use Illuminate\Support\Str;

/**
 * The overview for the edition picked in the cover dialog, found by its ISBN.
 */
final class BookEditionOverview
{
    public function __construct(private Client $openLibrary) {}

    /**
     * @return array{overview: string|null}
     */
    public function __invoke(string $isbn): array
    {
        $isbn = preg_replace('/[^0-9X]/i', '', $isbn) ?? '';
        $overview = $isbn === '' ? null : $this->openLibrary->description($isbn);

        return ['overview' => $overview === null ? null : Str::limit($overview, BookLookup::MAX_OVERVIEW, '')];
    }
}
