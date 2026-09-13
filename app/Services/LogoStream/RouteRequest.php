<?php

namespace App\Services\LogoStream;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Route metadata for a departure/arrival pair. */
class RouteRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $departureIata,
        private readonly string $arrivalIata,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v1/routes';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'departureIata' => $this->departureIata,
            'arrivalIata' => $this->arrivalIata,
            'limit' => 1,
        ];
    }
}
