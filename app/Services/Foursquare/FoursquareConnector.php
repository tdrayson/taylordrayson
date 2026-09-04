<?php

namespace App\Services\Foursquare;

use App\Services\ApiConnector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\OffsetPaginator;
use Saloon\PaginationPlugin\Paginator;

/** The Foursquare/Swarm v2 API. */
class FoursquareConnector extends ApiConnector implements HasPagination
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.foursquare.com/v2';
    }

    /**
     * The feed has no total or next-page marker, so an empty page is the only
     * signal that the history has run out.
     */
    public function paginate(Request $request): Paginator
    {
        return new class(connector: $this, request: $request) extends OffsetPaginator
        {
            protected function isLastPage(Response $response): bool
            {
                return empty($response->json('response.checkins.items'));
            }

            /** @return array<int, array<string, mixed>> */
            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json('response.checkins.items') ?? [];
            }
        };
    }
}
