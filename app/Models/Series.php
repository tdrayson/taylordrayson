<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[Fillable(['trakt_id', 'slug', 'title', 'year', 'overview', 'meta'])]
class Series extends Model implements HasMedia
{
    use HasAttachments, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'year' => 'integer',
        ];
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('occurred_at');
    }

    /**
     * Build a persisted, service-independent slug from intrinsic metadata:
     * base title slug, disambiguated by year, then by an incrementing suffix.
     *
     * @param  callable(string): bool  $exists  Returns true if the slug is taken.
     */
    public static function slugFor(string $title, ?int $year, callable $exists): string
    {
        $base = Str::slug($title);

        if (! $exists($base)) {
            return $base;
        }

        $withYear = $year ? "{$base}-{$year}" : $base;

        if ($year && ! $exists($withYear)) {
            return $withYear;
        }

        $candidate = $withYear;
        $suffix = 2;
        while ($exists($candidate)) {
            $candidate = "{$withYear}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Count distinct season+episode combinations watched, so rewatches of the
     * same episode don't inflate progress.
     */
    public function watchedEpisodeCount(): int
    {
        return $this->episodes
            ->map(fn (Media $m): string => ($m->meta['season'] ?? '?').'x'.($m->meta['episode'] ?? '?'))
            ->unique()
            ->count();
    }

    /**
     * Percentage of aired episodes watched, clamped to 100. Null when the
     * total aired episode count isn't known yet.
     */
    public function progress(): ?int
    {
        return $this->progressFromDistinct($this->watchedEpisodeCount());
    }

    /**
     * Same clamp/aired logic as progress(), but takes an already-computed
     * distinct-episode count so callers (e.g. an index page aggregating
     * across many shows in one query) don't need to hydrate `episodes`.
     */
    public function progressFromDistinct(int $distinctWatched): ?int
    {
        $aired = $this->meta['aired_episodes'] ?? null;
        if (! $aired) {
            return null;
        }

        return (int) min(100, round($distinctWatched / $aired * 100));
    }

    public function firstWatchedAt(): ?CarbonInterface
    {
        return $this->episodes->min('occurred_at');
    }

    public function lastWatchedAt(): ?CarbonInterface
    {
        return $this->episodes->max('occurred_at');
    }

    /**
     * Human-readable span between the first and last watched episode, e.g.
     * "over 8 months". Null when there's no watch history yet.
     */
    public function watchSpan(): ?string
    {
        $first = $this->firstWatchedAt();
        $last = $this->lastWatchedAt();
        if (! $first || ! $last) {
            return null;
        }

        return $first->isSameDay($last)
            ? 'in a single day'
            : 'over '.$first->diffForHumans($last, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
    }

    /**
     * Sum of episode runtimes across all watched rows, including rewatches.
     */
    public function totalRuntimeMinutes(): int
    {
        return (int) $this->episodes->sum(fn (Media $m): int => (int) ($m->meta['runtime'] ?? 0));
    }
}
