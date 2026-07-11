<?php

namespace App\Actions;

use App\Models\Article;
use App\Models\Page;
use App\Models\TimelineEntry;
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
    private function fromCard(Model $model, string $href): array
    {
        $card = $model->card();

        return [
            'url' => $href,
            'title' => $card['title'] ?? null,
            'excerpt' => $card['subtitle'] ?? null,
            'type' => $card['type'] ?? null,
            'accent' => $card['accent'] ?? $card['type'] ?? null,
            'date' => $model->occurredAtForDisplay()?->toDateString(),
            'cover' => data_get($card, 'meta.photos.0.src'),
        ];
    }
}
