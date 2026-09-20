<?php

namespace App\Actions\Citations;

use App\Models\Citation;

/** The stored copy of a post, fetched and stored first when there is none or a refresh is asked for. */
final class ResolveCitation
{
    public function __construct(
        private readonly FetchCitation $fetch,
        private readonly StoreCitation $store,
    ) {}

    public function __invoke(string $url, bool $refresh): ?Citation
    {
        $stored = Citation::query()->firstWhere('url', $url);

        if ($stored !== null && ! $refresh) {
            return $stored;
        }

        $data = ($this->fetch)($url);

        return $data === null ? $stored : ($this->store)($data);
    }
}
