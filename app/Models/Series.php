<?php

namespace App\Models;

use App\Data\SeriesMeta;
use App\Models\Concerns\HasAttachments;
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
            'meta' => SeriesMeta::class,
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

    /** The show's own page, which gathers every watched episode. */
    public function url(): string
    {
        return '/media/tv/'.$this->slug;
    }
}
