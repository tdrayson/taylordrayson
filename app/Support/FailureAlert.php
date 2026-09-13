<?php

namespace App\Support;

use App\Services\Pushover\Client;
use Illuminate\Support\Facades\Cache;

/**
 * Sends one alert per failing thing per hour.
 *
 * The throttle is the point. trakt:sync runs every minute, so an unthrottled
 * alert on a broken sync would be 1,440 notifications a day and muted within
 * the hour, which is worse than no alerting at all. One an hour says the same
 * thing and stays readable.
 */
class FailureAlert
{
    private const WINDOW = 3600;

    public function __construct(private readonly Client $pushover) {}

    /**
     * @param  string  $key  What is failing, e.g. the command name. Repeats
     *                       inside the window are dropped.
     */
    public function report(string $key, string $title, string $message): void
    {
        // add() only writes when the key is absent, so it is the lock and the
        // record of "already told them" in one call.
        if (! Cache::add('alert:'.md5($key), true, self::WINDOW)) {
            return;
        }

        $this->pushover->send($title, $message);
    }
}
