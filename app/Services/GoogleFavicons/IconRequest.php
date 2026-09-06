<?php

namespace App\Services\GoogleFavicons;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** A domain's favicon, at the only size worth asking for. */
class IconRequest extends Request
{
    protected Method $method = Method::GET;

    /** Retina-friendly and still small; the service only serves fixed sizes. */
    private const SIZE = 64;

    public function __construct(private readonly string $domain) {}

    public function resolveEndpoint(): string
    {
        return '';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['domain' => $this->domain, 'sz' => self::SIZE];
    }
}
