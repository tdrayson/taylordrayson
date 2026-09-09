<?php

namespace App\Actions\Snippetclub;

use App\Actions\Files\ResolveZightVideo;
use App\Data\SnippetclubImport;
use App\Models\Article;
use App\Support\EntryInstant;
use Illuminate\Support\Str;

/**
 * One exported WordPress post to an Article.
 *
 * One-off, for the SnippetClub migration. Delete it once the content has moved.
 */
final class ImportPost
{
    /** The source site's own clock, which its dates are already written in. */
    private const TIMEZONE = 'Europe/London';

    /** Categories that only ever meant "is this behind the paywall". */
    private const DROPPED_CATEGORIES = ['free', 'premium'];

    public function __construct(
        private readonly ConvertContent $convert,
        private readonly ResolveZightVideo $resolveVideo,
    ) {}

    /**
     * @param  array<string, mixed>  $post  One item from the export endpoint.
     */
    public function __invoke(array $post, bool $dryRun = false): SnippetclubImport
    {
        $converted = ($this->convert)(
            $post['content_raw'] ?? '',
            fn (string $url): ?array => ($this->resolveVideo)($url)?->toArray(),
        );

        $attributes = [
            'title' => $this->decoded($post['title'] ?? ''),
            'excerpt' => ($post['excerpt'] ?? '') !== '' ? $this->decoded($post['excerpt']) : null,
            'content' => $converted->nodes,
            'published' => ($post['status'] ?? '') === 'publish',
            'occurred_at' => $post['date'] ?? EntryInstant::nowLocal(),
            'timezone' => self::TIMEZONE,
        ];

        $slug = $this->slug($post);
        $tags = $this->tags($post);

        if ($dryRun) {
            return new SnippetclubImport(
                $slug,
                ! Article::where('slug', $slug)->exists(),
                count($converted->nodes),
                $tags,
                $converted->notes,
            );
        }

        $article = Article::updateOrCreate(['slug' => $slug], $attributes);
        $article->syncTagNames($tags);

        return new SnippetclubImport(
            $slug,
            $article->wasRecentlyCreated,
            count($converted->nodes),
            $tags,
            $converted->notes,
        );
    }

    /**
     * WordPress leaves `post_name` empty until a post is published, so five of
     * the drafts arrive with no slug at all and would collide on one row.
     *
     * @param  array<string, mixed>  $post
     */
    private function slug(array $post): string
    {
        $slug = trim((string) ($post['slug'] ?? ''));

        return $slug !== '' ? $slug : Str::slug((string) ($post['title'] ?? 'untitled'));
    }

    /**
     * The post's own tags plus whichever categories still say something. `free`
     * and `premium` described a paywall that no longer exists.
     *
     * @param  array<string, mixed>  $post
     * @return list<string>
     */
    private function tags(array $post): array
    {
        $names = array_column($post['tags'] ?? [], 'name');

        foreach ($post['categories'] ?? [] as $category) {
            if (! in_array($category['slug'] ?? '', self::DROPPED_CATEGORIES, true)) {
                $names[] = $category['name'];
            }
        }

        return array_values(array_unique(array_filter(array_map($this->decoded(...), $names))));
    }

    /**
     * WordPress stores titles and term names HTML-encoded, so `Hooks &amp;
     * Filters` arrives as those characters rather than as an ampersand.
     */
    private function decoded(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
    }
}
