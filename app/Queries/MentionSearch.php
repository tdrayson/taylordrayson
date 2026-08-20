<?php

namespace App\Queries;

use App\Models\Article;
use App\Models\Event;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Candidates for the @-mention menu, grouped by kind.
 *
 * Read-only and side-effect free. Only kinds worth pointing at from prose are
 * here: things that arrive from a sync (activities, check-ins, sleep) are not
 * what anyone reaches for mid-sentence.
 *
 * Drafts are not offered. A mention of one renders as plain text to everyone but
 * the author, so linking to it would read as finished writing while being a dead
 * end for every visitor.
 */
final class MentionSearch
{
    /**
     * Rows the menu shows in total. Past this the panel outgrows the viewport
     * and the caret it is anchored to stops being visible.
     */
    private const LIMIT = 20;

    /**
     * Rows one kind may contribute, applied before the groups are concatenated
     * rather than after. Capping the flat list instead would let whichever kind
     * sorts first swallow the whole limit and leave the others unrepresented on
     * an empty query, which is the point of grouping in the first place.
     */
    private const PER_GROUP = 5;

    /**
     * Fixed order, so the list does not reshuffle under the arrow keys as the
     * query narrows.
     *
     * @return list<array{kind: string, group: string, id: int, url: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        $results = [
            ...$this->articles($query),
            ...$this->pages($query),
            ...$this->projects($query),
            ...$this->events($query),
            ...$this->notes($query),
        ];

        return array_slice($results, 0, self::LIMIT);
    }

    /**
     * @return list<array{kind: string, group: string, id: int, url: string, label: string, detail: string|null}>
     */
    private function articles(string $query): array
    {
        return Article::query()
            ->where('published', true)
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->orderByDesc('occurred_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Article $article): array => [
                'kind' => 'article',
                'group' => 'Articles',
                'id' => $article->id,
                'url' => $article->url(),
                'label' => $article->title,
                'detail' => $article->occurred_at?->format('j M Y'),
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, group: string, id: int, url: string, label: string, detail: string|null}>
     */
    private function pages(string $query): array
    {
        return Page::query()
            ->where('published', true)
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->orderBy('title')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Page $page): array => [
                'kind' => 'page',
                'group' => 'Pages',
                'id' => $page->id,
                'url' => $page->url(),
                'label' => $page->title,
                'detail' => '/'.$page->slug,
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, group: string, id: int, url: string, label: string, detail: string|null}>
     */
    private function projects(string $query): array
    {
        return Project::query()
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->orderByDesc('occurred_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Project $project): array => [
                'kind' => 'project',
                'group' => 'Projects',
                'id' => $project->id,
                'url' => $project->url(),
                'label' => $project->title,
                'detail' => $project->status,
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, group: string, id: int, url: string, label: string, detail: string|null}>
     */
    private function events(string $query): array
    {
        return Event::query()
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->orderByDesc('occurred_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Event $event): array => [
                'kind' => 'event',
                'group' => 'Events',
                'id' => $event->id,
                'url' => $event->url(),
                'label' => $event->name,
                'detail' => $event->occurred_at?->format('j M Y'),
            ])
            ->all();
    }

    /**
     * Notes have no title, so the first words of the content stand in.
     *
     * @return list<array{kind: string, group: string, id: int, url: string, label: string, detail: string|null}>
     */
    private function notes(string $query): array
    {
        return Note::query()
            ->when($query !== '', fn ($builder) => $builder->where('content', 'like', "%{$query}%"))
            ->orderByDesc('occurred_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Note $note): array => [
                'kind' => 'note',
                'group' => 'Notes',
                'id' => $note->id,
                'url' => $note->url(),
                'label' => Str::limit(strip_tags((string) $note->content), 60),
                'detail' => $note->occurred_at?->format('j M Y'),
            ])
            ->all();
    }
}
