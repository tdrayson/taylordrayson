<?php

namespace App\Services\Pushover;

use App\Services\ApiConnector;
use Saloon\Http\Connector as SaloonConnector;

/**
 * The Pushover message API.
 *
 * Deliberately not an {@see ApiConnector}: this is the path that
 * reports a failure, so it must not sit retrying while a cron run waits on it.
 */
class Connector extends SaloonConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.pushover.net/1';
    }
}
