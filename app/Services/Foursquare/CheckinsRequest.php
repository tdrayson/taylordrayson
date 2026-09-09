<?php

namespace App\Services\Foursquare;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

/**
 * The account's own check-in history, newest first. `limit` and `offset` come
 * from the connector's paginator.
 */
class CheckinsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    /** The API is versioned by date rather than by path. */
    private const API_VERSION = '20240109';

    public function __construct(
        private readonly string $token,
        private readonly ?int $afterTimestamp = null,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/users/self/checkins';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return array_filter([
            'oauth_token' => $this->token,
            'v' => self::API_VERSION,
            'sort' => 'newestfirst',
            'afterTimestamp' => $this->afterTimestamp,
        ], fn (mixed $value): bool => $value !== null);
    }
}
