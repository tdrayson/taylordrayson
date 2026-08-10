<?php

namespace App\Support;

use App\Timeline\TypeRegistry;
use Illuminate\Routing\Route;

/**
 * Builds the feed-autodiscovery <link>s for the current route, advertising a
 * type-narrowed feed alongside the site-wide ones on any page with a `type` route
 * default. Rendered server-side in app.blade.php, not Inertia's client <Head>,
 * because feed readers parse HTML without running JavaScript.
 */
class FeedDiscovery
{
    /**
     * Feed formats: discovery MIME type => base feed URL and short label.
     *
     * @var array<string, array{url: string, label: string}>
     */
    private const FORMATS = [
        'application/atom+xml' => ['url' => '/feed', 'label' => 'Atom'],
        'application/rss+xml' => ['url' => '/feed/rss', 'label' => 'RSS'],
        'application/feed+json' => ['url' => '/feed/json', 'label' => 'JSON'],
    ];

    /**
     * Contextual feed links for the given route, or an empty list when the route
     * is not scoped to a single type.
     *
     * @return array<int, array{type: string, title: string, href: string}>
     */
    public static function forRoute(?Route $route): array
    {
        $type = self::routeType($route);

        if ($type === null) {
            return [];
        }

        $label = TypeRegistry::find($type)['label'];

        $links = [];

        foreach (self::FORMATS as $mime => $format) {
            $links[] = [
                'type' => $mime,
                'title' => "Taylor Drayson: {$label} ({$format['label']})",
                'href' => "{$format['url']}?types={$type}",
            ];
        }

        return $links;
    }

    /**
     * The single timeline type a route is scoped to, read from the `type` default
     * shared by every archive index and taxonomy route. Null when absent or unknown.
     */
    private static function routeType(?Route $route): ?string
    {
        $type = $route?->defaults['type'] ?? null;

        return is_string($type) && TypeRegistry::find($type) !== null ? $type : null;
    }
}
