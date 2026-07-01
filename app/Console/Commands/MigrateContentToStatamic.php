<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Support\EditorJsToBard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Statamic\Facades\Entry;

#[Signature('content:migrate')]
#[Description('Migrate Eloquent Article, Note, and Page rows into Statamic flat-file entries (idempotent)')]
class MigrateContentToStatamic extends Command
{
    public function handle(): int
    {
        $this->migrateArticles();
        $this->migrateNotes();
        $this->migratePages();

        $this->info('Content migration complete.');

        return self::SUCCESS;
    }

    private function migrateArticles(): void
    {
        Article::query()->each(function (Article $article): void {
            $slug = $article->slug();

            if ($this->entryExists('articles', $slug)) {
                $this->line("  skip articles/{$slug}");

                return;
            }

            Entry::make()
                ->collection('articles')
                ->slug($slug)
                ->date($article->occurred_at)
                ->published(! $article->draft)
                ->data([
                    'title' => $article->title,
                    'excerpt' => $article->excerpt,
                    'tags' => $article->tags ?? [],
                    'content' => EditorJsToBard::convert($article->content ?? []),
                ])
                ->save();

            $this->line("  migrated articles/{$slug}");
        });
    }

    private function migrateNotes(): void
    {
        Note::query()->each(function (Note $note): void {
            $slug = $note->slug();

            if ($this->entryExists('notes', $slug)) {
                $this->line("  skip notes/{$slug}");

                return;
            }

            $published = property_exists($note, 'draft') ? ! $note->draft : true;

            Entry::make()
                ->collection('notes')
                ->slug($slug)
                ->date($note->occurred_at)
                ->published($published)
                ->data([
                    'content' => EditorJsToBard::convert($note->content ?? []),
                ])
                ->save();

            $this->line("  migrated notes/{$slug}");
        });
    }

    private function migratePages(): void
    {
        Page::query()->each(function (Page $page): void {
            $slug = $page->slug;

            if ($this->entryExists('pages', $slug)) {
                $this->line("  skip pages/{$slug}");

                return;
            }

            Entry::make()
                ->collection('pages')
                ->slug($slug)
                ->published(! $page->draft)
                ->data([
                    'title' => $page->title,
                    'excerpt' => $page->excerpt,
                    'content' => EditorJsToBard::convert($page->content ?? []),
                ])
                ->save();

            $this->line("  migrated pages/{$slug}");
        });
    }

    private function entryExists(string $collection, string $slug): bool
    {
        return Entry::query()
            ->where('collection', $collection)
            ->where('slug', $slug)
            ->count() > 0;
    }
}
