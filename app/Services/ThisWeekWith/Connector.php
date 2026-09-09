<?php

namespace App\Services\ThisWeekWith;

use App\Services\ApiConnector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\PagedPaginator;
use Saloon\PaginationPlugin\Paginator;

/**
 * The "This Week With" podcast website API. Public, so no authentication.
 */
class Connector extends ApiConnector implements HasPagination
{
    public function resolveBaseUrl(): string
    {
        return 'https://www.thisweekwith.co.uk/wp-json/podcast/v1';
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * The host's IPv6 edge answers 401 where its IPv4 edge serves the API, so a
     * dual-stack server picks the failing leg. Pinned to v4 until they fix it.
     *
     * @return array<string, array<int, int>>
     */
    protected function defaultConfig(): array
    {
        return ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]];
    }

    /**
     * The endpoint is `page`/`per_page` with a `total_pages` count, which is
     * exactly what PagedPaginator applies, so only the stopping condition and
     * the item key are ours to define.
     */
    public function paginate(Request $request): Paginator
    {
        return new class(connector: $this, request: $request) extends PagedPaginator
        {
            /**
             * `page` has already advanced past the response being examined by
             * the time this runs, so the comparison is strictly greater than.
             */
            protected function isLastPage(Response $response): bool
            {
                return $this->page > (int) $response->json('total_pages', 1);
            }

            /** @return array<int, array<string, mixed>> */
            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json('episodes', []);
            }
        };
    }
}
