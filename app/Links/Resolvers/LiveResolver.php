<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Queries\NowState;

/**
 * The /now page. Its card carries the live figures, current as of the request
 * that rendered the page the link sits on: a preview that kept updating while
 * the tab sat open would mean a request per hover, for one card.
 */
class LiveResolver implements LinkResolver
{
    public function __construct(private NowState $now) {}

    public function resolve(string $path): ?LinkPreviewData
    {
        if ($path !== '/now') {
            return null;
        }

        $state = ($this->now)();
        $parts = array_filter([
            $this->place($state['location'] ?? null),
            $this->weather($state['weather'] ?? null),
            $this->steps($state['rings'] ?? null),
        ]);

        return LinkPreviewData::live($path, 'Now', $parts === [] ? null : implode(', ', $parts));
    }

    /**
     * @param  array<string, mixed>|null  $location
     */
    private function place(?array $location): ?string
    {
        return $location['city'] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $weather
     */
    private function weather(?array $weather): ?string
    {
        return isset($weather['temp']) ? round((float) $weather['temp']).'°C' : null;
    }

    /**
     * @param  array<string, mixed>|null  $rings
     */
    private function steps(?array $rings): ?string
    {
        return isset($rings['steps']) ? number_format((int) $rings['steps']).' steps' : null;
    }
}
