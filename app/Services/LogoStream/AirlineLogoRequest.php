<?php

namespace App\Services\LogoStream;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** One airline logo variant, keyed by IATA code. */
class AirlineLogoRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $iata,
        private readonly string $variant,
        private readonly int $size,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/airlines/iata/'.$this->iata;
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'key' => config('services.logostream.key'),
            'variant' => $this->variant,
            'format' => 'png',
            'size' => $this->size,
        ];
    }
}
