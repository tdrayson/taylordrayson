<?php

namespace App\Actions;

use App\Models\Article;
use App\Models\Concerns\Timelineable;
use App\Models\Page;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Stories\StoryRegistry;
use Illuminate\Database\Eloquent\Model;

class BuildLinkPreviews
{
    /**
     * Build a deduped map of internal-link hrefs to preview cards from Portable
     * Text content. External, unresolvable, and unpublished targets are omitted.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(?array $blocks): array
    {
        if ($blocks === null) {
            return [];
        }

        $hrefs = [];
        foreach ($blocks as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                $href = $def['href'] ?? null;
                if (($def['_type'] ?? null) === 'link' && is_string($href) && ! str_starts_with($href, 'http')) {
                    $hrefs[$href] = true;
                }
            }
        }

        $previews = [];
        foreach (array_keys($hrefs) as $href) {
            $preview = $this->resolve($href);
            if ($preview !== null) {
                $previews[$href] = $preview;
            }
        }

        return $previews;
    }

    /**
     * Resolve an internal href to its preview data, or null when not previewable.
     *
     * @return array<string, mixed>|null
     */
    private function resolve(string $href): ?array
    {
        // Entry permalink: /YYYY/MM/DD/slug
        if (preg_match('#^/(\d{4})/(\d{2})/(\d{2})/([a-z0-9-]+)$#', $href, $m) === 1) {
            $entry = TimelineEntry::query()
                ->with('timelineable')
                ->whereDate('occurred_at', "{$m[1]}-{$m[2]}-{$m[3]}")
                ->where('url_slug', $m[4])
                ->first();

            $model = $entry?->timelineable;

            if ($model === null) {
                return null;
            }

            if ($model instanceof Article && ! $model->published) {
                return null;
            }

            return $this->fromCard($model, $href);
        }

        // Data story: /stories/{slug}. Not a model, so it resolves through the
        // registry that already backs the story pages themselves.
        if (preg_match('#^/stories/([a-z0-9-]+)$#', $href, $m) === 1) {
            $story = app(StoryRegistry::class)->find($m[1]);

            if ($story === null) {
                return null;
            }

            $card = $story->card();

            return [
                'url' => $href,
                'title' => $card['title'],
                'excerpt' => $card['description'],
                'type' => 'story',
                'accent' => $card['accent'],
                'date' => null,
                'cover' => null,
                'coverDark' => null,
            ];
        }

        // Content page: /slug
        if (preg_match('#^/([a-z][a-z0-9-]*)$#', $href, $m) === 1) {
            $page = Page::query()->where('slug', $m[1])->where('published', true)->first();

            if ($page === null) {
                return null;
            }

            return [
                'url' => $href,
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'type' => 'page',
                'accent' => 'page',
                'date' => null,
                'cover' => null,
                'coverDark' => null,
            ];
        }

        return null;
    }

    /**
     * Preview data from a timelineable model's card() metadata. Card subtitle
     * lives at the top level (not nested under meta); only photos nest there.
     *
     * @return array<string, mixed>
     */
    private function fromCard(Model&Timelineable $model, string $href): array
    {
        $card = CardPresenter::for($model);
        // Same order the timeline card prefers: a real photo, then a poster or
        // artwork, then the stored route/location map. Only the map has a dark
        // variant, so coverDark stays null for the other two.
        $cover = $card->meta->photos[0]->src
            ?? $card->meta->media?->thumbnail
            ?? $card->meta->map;

        return [
            'url' => $href,
            'title' => $card->title,
            'excerpt' => $card->subtitle,
            'type' => $card->type->value,
            'accent' => $card->accent,
            'date' => $model->occurredAtForDisplay()?->toDateString(),
            'cover' => $cover,
            'coverDark' => $cover === $card->meta->map ? $card->meta->mapDark : null,
        ];
    }
}
