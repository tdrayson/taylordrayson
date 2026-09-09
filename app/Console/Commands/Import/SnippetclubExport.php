<?php

namespace App\Console\Commands\Import;

use App\Models\Article;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Freeze the reviewed SnippetClub articles into one file, and put them back.
 *
 * The review happens locally, so production never runs the WordPress conversion
 * at all: it restores the result. Delete both halves once the content has moved.
 */
#[Signature('snippetclub:export {--restore : Read the file back into articles instead of writing it} {--path= : Defaults to storage/app/snippetclub/articles.json}')]
#[Description('Write the imported SnippetClub articles to one JSON file, or restore them from it')]
class SnippetclubExport extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path('app/snippetclub/articles.json');

        return $this->option('restore') ? $this->restore($path) : $this->write($path);
    }

    /** Every article whose slug came from the export, with its tags. */
    private function write(string $path): int
    {
        $slugs = $this->sourceSlugs();

        if ($slugs === []) {
            $this->components->error('No export found in storage/app/snippetclub, so there is nothing to identify.');

            return self::FAILURE;
        }

        $articles = Article::whereIn('slug', $slugs)->orderBy('occurred_at')->get()
            ->map(fn (Article $article): array => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'published' => $article->published,
                'occurred_at' => $article->occurred_at?->format('Y-m-d H:i:s'),
                'timezone' => $article->timezone,
                'tags' => $article->tagNames(),
            ])
            ->all();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->components->info(sprintf('Wrote %d articles to %s (%s).', count($articles), $path, $this->size($path)));

        return self::SUCCESS;
    }

    /** Idempotent on slug, so restoring twice is the same as restoring once. */
    private function restore(string $path): int
    {
        if (! File::exists($path)) {
            $this->components->error('No file at '.$path.'.');

            return self::FAILURE;
        }

        $articles = json_decode(File::get($path), true);

        if (! is_array($articles)) {
            $this->components->error('That file is not a list of articles.');

            return self::FAILURE;
        }

        $created = 0;

        foreach ($articles as $row) {
            $tags = $row['tags'] ?? [];
            unset($row['tags']);

            $article = Article::updateOrCreate(['slug' => $row['slug']], $row);
            $article->syncTagNames($tags);

            $article->wasRecentlyCreated ? $created++ : null;
            $this->components->task($row['slug']);
        }

        $this->newLine();
        $this->components->info(sprintf('Restored %d articles. %d new, %d already here.', count($articles), $created, count($articles) - $created));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function sourceSlugs(): array
    {
        $slugs = [];

        foreach (File::glob(storage_path('app/snippetclub/posts_*.json')) as $file) {
            foreach (json_decode(File::get($file), true)['items'] ?? [] as $item) {
                $slug = trim((string) ($item['slug'] ?? ''));
                $slugs[] = $slug !== '' ? $slug : str($item['title'] ?? 'untitled')->slug()->value();
            }
        }

        return array_values(array_unique($slugs));
    }

    private function size(string $path): string
    {
        return round(File::size($path) / 1024 / 1024, 1).' MB';
    }
}
