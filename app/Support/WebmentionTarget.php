<?php

namespace App\Support;

use App\Models\Page;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves a webmention `target` URL to the thing it is about.
 *
 * The spec requires an endpoint to reject a target it does not accept mentions
 * for, so this is an allowlist of two route names rather than "whatever the
 * URL happens to hit". Everything else on the site is a listing: a mention
 * against `/2026/08` or `/tags/coffee` has nobody to notify and nothing to
 * thread under.
 *
 * The URL is matched against the real router, so it stays honest with
 * routes/web.php rather than reimplementing its patterns.
 */
final class WebmentionTarget
{
    public static function resolve(string $url): ?Model
    {
        if (! self::isOurs($url)) {
            return null;
        }

        $model = self::routeTo($url);

        return $model !== null && InteractionTarget::accepts($model) ? $model : null;
    }

    /**
     * Whether a URL is on this site. A `target` elsewhere has to be refused or
     * the endpoint is an open relay; a `source` here is a self-ping, which is
     * already rendered as a link preview and would only duplicate it.
     */
    public static function isOurs(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host !== false
            && strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) === 0;
    }

    private static function routeTo(string $url): ?Model
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        try {
            $route = Route::getRoutes()->match(Request::create($path));
        } catch (HttpException) {
            return null;
        }

        return match ($route->getName()) {
            'entry' => self::entry($route->parameters()),
            'page' => Page::query()->where('slug', $route->parameter('slug'))->first(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function entry(array $parameters): ?Model
    {
        return TimelineEntry::query()
            ->whereDate('occurred_at', sprintf(
                '%04d-%02d-%02d',
                (int) $parameters['year'],
                (int) $parameters['month'],
                (int) $parameters['day'],
            ))
            ->where('url_slug', $parameters['slug'])
            ->first()?->timelineable;
    }
}
