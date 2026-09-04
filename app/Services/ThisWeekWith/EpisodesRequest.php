<?php

namespace App\Services\ThisWeekWith;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

/**
 * Published episodes, newest first. Paged by the connector's paginator, which
 * supplies `page` and `per_page`.
 */
class EpisodesRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/episodes';
    }
}
