<?php

namespace App\Search;

use App\Content\ContentEntry;
use App\Content\ContentRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Searches Statamic-sourced articles and notes for both the command-palette
 * suggest endpoint and the advanced query builder. This is the single source
 * of truth for article/note results, replacing the stale Eloquent morph copies
 * so a migrated post is matched once, from Statamic only.
 */
class ContentSearch
{
    public function __construct(private readonly ContentRepository $content) {}

    /**
     * Free-text matches for the command palette: match the term against the
     * entry title, excerpt, plain-text body, and (article) tags, newest first.
     *
     * @return Collection<int, ContentEntry> Published article/note matches.
     */
    public function suggest(string $term, int $perType): Collection
    {
        $needle = Str::lower($term);

        $articles = $this->content->articles()
            ->filter(fn (ContentEntry $entry): bool => $this->matchesText($entry, $needle))
            ->take($perType);

        $notes = $this->content->notes()
            ->filter(fn (ContentEntry $entry): bool => $this->matchesText($entry, $needle))
            ->take($perType);

        return $articles->merge($notes)->values();
    }

    /**
     * Advanced-search matches for one validated group over the 'article' or
     * 'note' type. Every condition must pass (AND-within-group), mirroring the
     * Eloquent compiler. Unsupported conditions are ignored, matching how the
     * compiler silently drops clauses it cannot apply.
     *
     * @param  array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}  $group
     * @return Collection<int, ContentEntry> The matching entries, newest first.
     */
    public function advanced(array $group): Collection
    {
        $entries = $group['type'] === 'note'
            ? $this->content->notes()
            : $this->content->articles();

        return $entries
            ->filter(fn (ContentEntry $entry): bool => $this->matchesConditions($entry, $group['conditions']))
            ->values();
    }

    /**
     * Match the "Anything" free-text clause against all published content, used
     * when an advanced-search group has type 'any'. Mirrors suggest() matching
     * but returns every hit (no per-type cap) for accurate result totals.
     *
     * @return Collection<int, ContentEntry>
     */
    public function anyText(string $term): Collection
    {
        $needle = Str::lower($term);

        return $this->content->all()
            ->filter(fn (ContentEntry $entry): bool => $this->matchesText($entry, $needle))
            ->values();
    }

    /**
     * True when the lowercased needle appears in the entry title, excerpt,
     * plain-text body, or any article tag.
     */
    private function matchesText(ContentEntry $entry, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return str_contains(Str::lower($this->haystack($entry)), $needle);
    }

    /**
     * The full searchable text for an entry: title, excerpt, tags, and the
     * plain-text body (HTML stripped from the rendered Bard output).
     */
    private function haystack(ContentEntry $entry): string
    {
        return implode(' ', array_filter([
            $entry->title(),
            $entry->excerpt(),
            implode(' ', $entry->tags()),
            trim(preg_replace('/\s+/', ' ', strip_tags($entry->bodyHtml()))),
        ]));
    }

    /**
     * Whether an entry satisfies every condition in an advanced-search group.
     *
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $conditions
     */
    private function matchesConditions(ContentEntry $entry, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->matchesCondition($entry, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single advanced-search condition against an entry.
     *
     * Supported fields: title/excerpt/content (text operators), and the shared
     * day/month/year date fields. Unsupported fields pass through (ignored) so
     * they never falsely exclude an entry, matching the Eloquent compiler which
     * silently drops clauses it cannot apply.
     *
     * @param  array{field: string, operator: string, value: mixed}  $condition
     */
    private function matchesCondition(ContentEntry $entry, array $condition): bool
    {
        return match ($condition['field']) {
            'title' => $this->textMatch($entry->title(), $condition['operator'], $condition['value']),
            'excerpt' => $this->textMatch((string) $entry->excerpt(), $condition['operator'], $condition['value']),
            // Content spans the body text plus tags, so a tag search resolves here.
            'content' => $this->textMatch($this->bodyAndTags($entry), $condition['operator'], $condition['value']),
            'day', 'month', 'year' => $this->dateMatch($entry->occurredAt(), $condition['field'], $condition['operator'], $condition['value']),
            default => true,
        };
    }

    /** Plain-text body plus tags, the searchable "content" of an entry. */
    private function bodyAndTags(ContentEntry $entry): string
    {
        return implode(' ', array_filter([
            trim(preg_replace('/\s+/', ' ', strip_tags($entry->bodyHtml()))),
            implode(' ', $entry->tags()),
        ]));
    }

    /**
     * Apply a text operator (case-insensitive) as the Eloquent compiler would.
     */
    private function textMatch(string $subject, string $operator, mixed $value): bool
    {
        $subject = Str::lower($subject);
        $needle = Str::lower((string) $value);

        return match ($operator) {
            'contains' => str_contains($subject, $needle),
            'not_contains' => ! str_contains($subject, $needle),
            'equals' => $subject === $needle,
            'starts_with' => str_starts_with($subject, $needle),
            'ends_with' => str_ends_with($subject, $needle),
            default => true,
        };
    }

    /**
     * Apply a day/month/year comparison against the entry date, mirroring the
     * Eloquent compiler's day/period semantics at day granularity.
     */
    private function dateMatch(Carbon|CarbonInterface $date, string $field, string $operator, mixed $value): bool
    {
        $day = Carbon::instance($date)->startOfDay();

        if ($operator === 'between' || $operator === 'not_between') {
            $range = $this->resolveRange($field, $value);

            if ($range === null) {
                return true;
            }

            $inside = $day->betweenIncluded($range[0], $range[1]);

            return $operator === 'not_between' ? ! $inside : $inside;
        }

        $bounds = $this->resolveBounds($field, (string) $value);

        if ($bounds === null) {
            return true;
        }

        return match ($operator) {
            'on', 'in' => $day->betweenIncluded($bounds[0], $bounds[1]),
            'not_on', 'not_in' => ! $day->betweenIncluded($bounds[0], $bounds[1]),
            'before' => $day->lt($bounds[0]),
            'after' => $day->gt($bounds[1]),
            default => true,
        };
    }

    /**
     * Resolve a single day/month/year value to its inclusive [start, end] bounds.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function resolveBounds(string $field, string $value): ?array
    {
        return match ($field) {
            'day' => $this->dayBounds($value),
            'month' => $this->monthBounds($value),
            'year' => $this->yearBounds($value),
            default => null,
        };
    }

    /**
     * Resolve a [from, to] pair to an ordered inclusive span for the given field.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function resolveRange(string $field, mixed $value): ?array
    {
        if (! is_array($value) || count($value) !== 2) {
            return null;
        }

        $from = $this->resolveBounds($field, (string) $value[0]);
        $to = $this->resolveBounds($field, (string) $value[1]);

        if ($from === null || $to === null) {
            return null;
        }

        $start = $from[0]->lte($to[0]) ? $from[0] : $to[0];
        $end = $from[1]->gte($to[1]) ? $from[1] : $to[1];

        return [$start, $end];
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function dayBounds(string $value): ?array
    {
        $day = substr($value, 0, 10);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();

        return [$start, $start->copy()->endOfDay()];
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function monthBounds(string $value): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$value}-01")->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function yearBounds(string $value): ?array
    {
        if (! preg_match('/^\d{4}$/', $value)) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$value}-01-01")->startOfYear();

        return [$start, $start->copy()->endOfYear()];
    }
}
