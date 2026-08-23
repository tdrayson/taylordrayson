<?php

namespace App\Console\Commands\Import;

use App\Models\Article;
use App\Support\HtmlToPortableText;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

#[Signature('import:articles {--source=https://taylordrayson.com : WordPress site to read from} {--only=* : Limit to these post slugs} {--draft : Import as drafts instead of publishing}')]
#[Description('Import the old WordPress posts as articles: body converted to Portable Text, categories become tags, and every image re-attached to local storage')]
class ImportWordPressArticles extends Command
{
    /** WordPress numbers its own uncategorised bucket 1 and it means nothing here. */
    private const SKIPPED_CATEGORY = 'uncategorized';

    /**
     * Re-runnable: an article is matched by slug and rebuilt in place, media
     * included, so the import can be corrected and run again without leaving
     * duplicates behind.
     */
    public function handle(): int
    {
        $source = rtrim((string) $this->option('source'), '/');
        $only = (array) $this->option('only');

        try {
            $categories = $this->categories($source);
            $posts = $this->posts($source);
        } catch (Throwable $exception) {
            $this->components->error("Could not read {$source}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if ($only !== []) {
            $posts = array_values(array_filter($posts, fn (array $post): bool => in_array($post['slug'], $only, true)));
        }

        if ($posts === []) {
            $this->components->warn('No posts to import.');

            return self::SUCCESS;
        }

        foreach ($posts as $post) {
            $this->import($post, $categories, ! $this->option('draft'));
        }

        $this->components->info('Imported '.count($posts).' article(s).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<int, string>  $categories
     */
    private function import(array $post, array $categories, bool $published): void
    {
        $article = Article::firstOrNew(['slug' => $post['slug']]);

        $article->fill([
            'title' => $this->decode($post['title']['rendered'] ?? ''),
            // WordPress reports `date` in the site's own timezone, which is the
            // local wall-clock reading occurred_at stores.
            'occurred_at' => $post['date'],
            'timezone' => config('app.home_timezone'),
            'published' => $published,
            // The WordPress excerpt is the opening of the post, not a written
            // summary, so importing it would print the first paragraph twice.
            'excerpt' => null,
            'content' => HtmlToPortableText::convert($post['content']['rendered'] ?? ''),
        ])->save();

        $article->syncTagNames($this->tagNames($post, $categories));

        $article->clearMediaCollection('cover');
        $article->clearMediaCollection('body');

        $cover = $this->coverUrl($post);

        if ($cover !== null) {
            $this->attach($article, $cover, 'cover');
        }

        $article->content = $this->localised($article, $article->content);
        $article->save();

        $this->components->twoColumnDetail(
            $post['slug'],
            sprintf(
                '%d image(s), %s',
                $article->getMedia('body')->count() + $article->getMedia('cover')->count(),
                implode(', ', $article->tagNames()) ?: 'no tags',
            ),
        );
    }

    /**
     * Move every image the document points at into the article's own storage,
     * rewriting each node to the attachment's URL and its stored dimensions.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function localised(Article $article, array $nodes): array
    {
        $seen = [];

        foreach ($nodes as $index => $node) {
            if (($node['_type'] ?? null) !== 'image') {
                continue;
            }

            $url = $node['url'];

            // The same image can appear twice in a post; attach it once.
            if (! array_key_exists($url, $seen)) {
                $media = $this->attach($article, $url, 'body');
                $seen[$url] = $media === null ? null : $this->imageAttributes($media);
            }

            if ($seen[$url] !== null) {
                $nodes[$index] = [...$node, ...$seen[$url]];
            }
        }

        return $nodes;
    }

    private function attach(Article $article, string $url, string $collection): ?Media
    {
        try {
            return $article->addMediaFromUrl($url)->toMediaCollection($collection);
        } catch (Throwable $exception) {
            $this->components->warn("{$article->slug}: could not fetch {$url} ({$exception->getMessage()})");

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function imageAttributes(Media $media): array
    {
        $attributes = ['url' => $media->getUrl()];

        try {
            $image = Image::load($media->getPath());
            $attributes['width'] = $image->getWidth();
            $attributes['height'] = $image->getHeight();
        } catch (Throwable) {
            // Dimensions only tune the layout; the image renders without them.
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<int, string>  $categories
     * @return list<string>
     */
    private function tagNames(array $post, array $categories): array
    {
        return array_values(array_filter(array_map(
            fn (int $id): ?string => $categories[$id] ?? null,
            $post['categories'] ?? [],
        )));
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function coverUrl(array $post): ?string
    {
        $media = $post['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null;

        return is_string($media) && $media !== '' ? $media : null;
    }

    /**
     * Category id => display name, which is what the site's tags are keyed by.
     *
     * @return array<int, string>
     */
    private function categories(string $source): array
    {
        $names = [];

        foreach ($this->get($source, 'categories', ['_fields' => 'id,name,slug']) as $category) {
            if ($category['slug'] !== self::SKIPPED_CATEGORY) {
                $names[$category['id']] = $this->decode($category['name']);
            }
        }

        return $names;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(string $source): array
    {
        return $this->get($source, 'posts', [
            '_embed' => 'wp:featuredmedia',
            '_fields' => 'date,slug,title,content,categories,_links,_embedded',
        ]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<int, array<string, mixed>>
     */
    private function get(string $source, string $resource, array $query): array
    {
        return Http::timeout(30)
            ->throw()
            ->get("{$source}/wp-json/wp/v2/{$resource}", [...$query, 'per_page' => 100])
            ->json();
    }

    private function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
