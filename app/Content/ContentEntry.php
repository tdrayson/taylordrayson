<?php

namespace App\Content;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;

/**
 * Typed seam wrapping a Statamic Entry from the articles, notes, or pages
 * collection. Provides a stable interface consumed by timeline, archives,
 * entry-detail, feeds, search, and OG tasks (Tasks 10-15).
 */
class ContentEntry
{
    public function __construct(
        private readonly Entry $entry,
        private readonly BardRenderer $bard,
    ) {}

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    /** Collection handle: 'article', 'note', or 'page' (singular). */
    public function type(): string
    {
        $handle = $this->entry->collectionHandle();

        // Statamic collection handles are plural; we expose the singular form.
        return match ($handle) {
            'articles' => 'article',
            'notes' => 'note',
            'pages' => 'page',
            default => $handle,
        };
    }

    public function slug(): string
    {
        return (string) $this->entry->slug();
    }

    // -------------------------------------------------------------------------
    // Date / URL (dated collections only: articles, notes)
    // -------------------------------------------------------------------------

    /** Entry date as a Carbon instance. */
    public function occurredAt(): CarbonInterface
    {
        return Carbon::instance($this->entry->date());
    }

    /**
     * Public URL in the form /{Y}/{m}/{d}/{slug}.
     * Only valid for dated collections (articles, notes).
     */
    public function url(): string
    {
        $date = $this->occurredAt();

        return sprintf(
            '/%s/%s/%s/%s',
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $this->slug(),
        );
    }

    // -------------------------------------------------------------------------
    // Fields
    // -------------------------------------------------------------------------

    public function title(): string
    {
        return (string) $this->entry->get('title');
    }

    public function excerpt(): ?string
    {
        $value = $this->entry->get('excerpt');

        return $value !== null ? (string) $value : null;
    }

    public function bodyHtml(): string
    {
        return $this->bard->toHtml($this->entry->augmentedValue('content'));
    }

    /**
     * Tags stored as an array on the entry; returns an empty array when absent.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        $value = $this->entry->get('tags');

        return is_array($value) ? $value : [];
    }

    public function isDraft(): bool
    {
        return ! $this->entry->published();
    }

    // -------------------------------------------------------------------------
    // Card (Timelineable parity)
    // -------------------------------------------------------------------------

    /**
     * Returns the same shape as the legacy Timelineable::card() contract:
     * type, icon, title, subtitle, occurred_at, accent, meta.
     *
     * Article: title from the title field, subtitle from excerpt.
     * Note: title derived from Bard plain-text (80 chars), subtitle null.
     *
     * @return array{type: string, icon: string, title: string, subtitle: string|null, occurred_at: CarbonInterface, accent: string, meta: array<mixed>}
     */
    public function card(): array
    {
        return match ($this->type()) {
            'article' => $this->articleCard(),
            'note' => $this->noteCard(),
            default => $this->articleCard(),
        };
    }

    /** @return array<string, mixed> */
    private function articleCard(): array
    {
        return [
            'type' => 'article',
            'icon' => 'file-text',
            'title' => $this->title(),
            'subtitle' => $this->excerpt(),
            'occurred_at' => $this->occurredAt(),
            'accent' => 'article',
            'meta' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function noteCard(): array
    {
        return [
            'type' => 'note',
            'icon' => 'message-circle',
            'title' => Str::limit($this->bardPlainText(), 80),
            'subtitle' => null,
            'occurred_at' => $this->occurredAt(),
            'accent' => 'note',
            'meta' => [],
        ];
    }

    /**
     * Extract plain text from the Bard (ProseMirror/Tiptap) node tree stored
     * on the entry's content field. Traverses the node tree recursively and
     * collects all text-node values.
     */
    private function bardPlainText(): string
    {
        $rawContent = $this->entry->get('content');

        if (! is_array($rawContent)) {
            return '';
        }

        $parts = [];
        $this->collectTextNodes($rawContent, $parts);

        $text = implode(' ', $parts);

        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    /**
     * Recursively walk ProseMirror nodes collecting text values.
     *
     * @param  array<mixed>  $nodes
     * @param  array<int, string>  &$parts
     */
    private function collectTextNodes(array $nodes, array &$parts): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            // A text node has a 'text' key with the literal string value.
            if (isset($node['text']) && is_string($node['text'])) {
                $parts[] = $node['text'];
            }

            // Recurse into child nodes if present.
            if (isset($node['content']) && is_array($node['content'])) {
                $this->collectTextNodes($node['content'], $parts);
            }
        }
    }
}
