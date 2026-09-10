<?php

namespace App\Actions\Snippetclub;

use App\Models\Article;

/**
 * Point the old site's own links at where those pages live now.
 *
 * Runs after every post is in, so an article linking forward to one imported
 * later still resolves. One-off, delete it with the rest of the migration.
 */
final class RewriteLinks
{
    private const HOSTS = ['snippetclub.com', 'www.snippetclub.com'];

    /**
     * Paths with a home that is not an article. The category archives became
     * tags, and the old contact form and support address are one page here.
     *
     * @var array<string, string>
     */
    private const PAGES = [
        '/contact' => '/contact',
        '/latest' => '/articles',
        '/tutorials' => '/tags/tutorials',
        '/snippets' => '/tags/snippets',
        '/free' => '/articles',
        '/premium' => '/articles',
        'mailto:support@snippetclub.com' => '/contact',
    ];

    /** @var array<string, string> slug => the article's dated path */
    private array $articles = [];

    /** @var list<string> */
    private array $unresolved = [];

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{nodes: list<array<string, mixed>>, rewritten: int, unresolved: list<string>}
     */
    public function __invoke(array $nodes): array
    {
        $this->articles === [] && $this->loadArticles();
        $this->unresolved = [];

        $rewritten = 0;
        $nodes = $this->walk($nodes, $rewritten);

        return ['nodes' => $nodes, 'rewritten' => $rewritten, 'unresolved' => array_values(array_unique($this->unresolved))];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function walk(array $nodes, int &$rewritten): array
    {
        foreach ($nodes as $index => $node) {
            foreach ($node['markDefs'] ?? [] as $position => $def) {
                $target = $this->target($def['href'] ?? '');

                if ($target !== null && $target !== $def['href']) {
                    $nodes[$index]['markDefs'][$position]['href'] = $target;
                    $rewritten++;
                }
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $nodes[$index]['children'] = $this->walk($node['children'], $rewritten);
            }
        }

        return $nodes;
    }

    /** Where a link should point now, or null when it is not ours to move. */
    private function target(string $href): ?string
    {
        if (isset(self::PAGES[$href])) {
            return self::PAGES[$href];
        }

        if (! in_array(strtolower((string) parse_url($href, PHP_URL_HOST)), self::HOSTS, true)) {
            return null;
        }

        $path = rtrim((string) parse_url($href, PHP_URL_PATH), '/');
        $slug = ltrim($path, '/');

        if (isset(self::PAGES[$path])) {
            return self::PAGES[$path];
        }

        if (isset($this->articles[$slug])) {
            return $this->articles[$slug];
        }

        // A link to the old site with nowhere to go here. Left alone so the
        // review can decide, since the site is still up to be read.
        $this->unresolved[] = $href;

        return null;
    }

    private function loadArticles(): void
    {
        foreach (Article::with('timelineEntry')->get() as $article) {
            $entry = $article->timelineEntry;

            if ($entry !== null) {
                $this->articles[$article->slug] = '/'.$entry->occurred_at->format('Y/m/d').'/'.$article->slug;
            }
        }
    }
}
