<?php

namespace App\Actions;

use App\Models\Activity;
use App\Services\Strava\Client;
use App\Support\Downsample;
use Carbon\CarbonImmutable;

class StoreActivityStreams
{
    private const KEYS = ['time', 'distance', 'latlng', 'altitude', 'velocity_smooth', 'heartrate'];

    private const CAP = 240;

    /**
     * Fetch, downsample, and store the activity's Strava streams. Returns true
     * when at least one series was stored.
     */
    public function __invoke(Activity $activity, Client $strava): bool
    {
        $streams = $strava->activityStreams($activity->source_id, self::KEYS);

        $time = $streams['time']['data'] ?? null;
        if (! is_array($time) || $time === []) {
            return false;
        }

        $start = CarbonImmutable::parse($activity->occurred_at);
        $indices = Downsample::indices(count($time), self::CAP);
        $stamp = fn (int $i): string => $start->addSeconds((int) $time[$i])->format('Y-m-d H:i:s');

        $altitude = $streams['altitude']['data'] ?? null;
        $speed = $streams['velocity_smooth']['data'] ?? null;
        $latlng = $streams['latlng']['data'] ?? null;
        $heartrate = $streams['heartrate']['data'] ?? null;

        $attributes = [];

        if (is_array($altitude)) {
            $attributes['altitude'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'value' => $altitude[$i]], $indices);
        }
        if (is_array($speed)) {
            $attributes['speed'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'value' => $speed[$i]], $indices);
        }
        if (is_array($latlng)) {
            $attributes['track'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'lat' => $latlng[$i][0], 'lng' => $latlng[$i][1]], $indices);
        }
        if (is_array($heartrate)) {
            $attributes['heart_rate'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'bpm' => $heartrate[$i]], $indices);
        }

        if ($attributes === []) {
            return false;
        }

        $activity->update($attributes);

        return true;
    }
}
