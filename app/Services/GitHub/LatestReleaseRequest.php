<?php

namespace App\Services\GitHub;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The newest published release of a repo, with its assets. */
class LatestReleaseRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  string  $repo  In owner/name form. */
    public function __construct(private readonly string $repo) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->repo.'/releases/latest';
    }
}
