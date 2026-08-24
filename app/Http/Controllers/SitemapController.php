<?php

namespace App\Http\Controllers;

use App\Queries\SitemapUrls;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The sitemap index and its sections.
 *
 * Ten thousand entries in one file would be rebuilt in full every time a
 * crawler asked for any of it, so the index points at a page of fixed URLs plus
 * one file per year. Each is cached, since a sitemap that is a few minutes
 * behind costs nothing and rebuilding on every request costs a scan of the
 * spine.
 */
class SitemapController extends Controller
{
    private const CACHE_MINUTES = 60;

    public function __construct(private readonly SitemapUrls $urls) {}

    /**
     * The index listing every section file.
     */
    public function index(): Response
    {
        $sitemaps = $this->cached('sitemap:index', function (): array {
            $years = array_map(fn (int $year): array => [
                'loc' => route('sitemap.year', ['year' => $year]),
                'lastmod' => $this->urls->yearLastModified($year),
            ], $this->urls->years());

            return [['loc' => route('sitemap.pages'), 'lastmod' => null], ...$years];
        });

        return $this->xml(view('sitemap.index', ['sitemaps' => $sitemaps]));
    }

    /**
     * Everything that is not a dated entry.
     */
    public function pages(): Response
    {
        return $this->xml(view('sitemap.urlset', [
            'urls' => $this->cached('sitemap:pages', fn (): array => $this->urls->pages()),
        ]));
    }

    /**
     * One year of entries and the dated pages listing them.
     */
    public function year(int $year): Response
    {
        $urls = $this->cached("sitemap:year:{$year}", fn (): array => $this->urls->year($year));

        abort_if($urls === [], 404);

        return $this->xml(view('sitemap.urlset', ['urls' => $urls]));
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private function cached(string $key, callable $build): array
    {
        return Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), $build);
    }

    /**
     * The rendered view behind an XML declaration.
     *
     * The declaration is prepended here rather than written at the top of the
     * template: Blade leaves a `<?xml` line uncompiled and passes it through as
     * literal text, taking the rest of the template with it.
     */
    private function xml(View $view): Response
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".$view->render(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }
}
