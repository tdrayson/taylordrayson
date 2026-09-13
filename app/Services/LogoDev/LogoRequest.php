<?php

namespace App\Services\LogoDev;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * A brand logo PNG by web domain.
 *
 * `fallback=404` is set so a miss 404s instead of returning a generated
 * monogram placeholder, which would otherwise be saved as if it were real.
 */
class LogoRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $domain,
        private readonly int $size,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/'.$this->domain;
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'token' => config('services.logodev.token'),
            'size' => $this->size,
            'retina' => 'true',
            'format' => 'png',
            'fallback' => '404',
        ];
    }
}
