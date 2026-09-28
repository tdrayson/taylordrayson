<?php

namespace App\Timeline;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The feed preset the home timeline is narrowed to, remembered per visitor in
 * a cookie so the paging links stay bare.
 */
final class TimelineFilter
{
    public const COOKIE = 'timeline_filter';

    public const DEFAULT = 'curated';

    /** The visitor's preset key, or the default when unset or unknown. */
    public static function for(Request $request): string
    {
        $filter = $request->cookie(self::COOKIE);

        return is_string($filter) && FeedPresets::types($filter) !== null ? $filter : self::DEFAULT;
    }

    /** The preset asked for in the query string, when it names one. */
    public static function requested(Request $request): ?string
    {
        $filter = $request->query('filter');

        return is_string($filter) && FeedPresets::types($filter) !== null ? $filter : null;
    }

    /** A year-long cookie remembering the preset. */
    public static function remember(string $filter): Cookie
    {
        return cookie(self::COOKIE, $filter, 60 * 24 * 365);
    }

    /**
     * The dataset keys for a preset, or null when it covers every one.
     *
     * @return array<int, string>|null
     */
    public static function datasets(string $filter): ?array
    {
        return $filter === 'everything' ? null : FeedPresets::types($filter);
    }
}
