<?php

namespace App\Models;

use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use App\Support\Links;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'source_url',
    'parent_source_url',
    'target_url',
    'kind',
    'title',
    'author_name',
    'author_url',
    'author_host',
    'author_photo_path',
    'author_photo_url',
    'content',
    'published_at',
    'timezone',
    'status',
    'verified_at',
    'last_checked_at',
])]
class Webmention extends Model
{
    use HasFactory;

    /**
     * Take the nested responses with the mention that carried them.
     *
     * A salmention is only ever visible because the page it was read from is:
     * left behind when that page stops linking here, it would keep a stranger's
     * comment thread on the entry with nothing above it to explain why.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $mention): void {
            self::query()
                ->where('parent_source_url', $mention->source_url)
                ->where('target_url', $mention->target_url)
                ->delete();
        });
    }

    /**
     * `kind` is deliberately not cast. Its values come from other people's
     * sites, and an enum cast throws the moment one we do not know is read back.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => CommentStatus::class,
            'published_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * The sending site's host, kept in step with the URL it comes from.
     *
     * Derived rather than assigned so the two can never disagree: the host is
     * what moderation trusts, and a writer that set the URL but forgot the host
     * would silently make a known sender look like a stranger.
     */
    protected function authorUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): array => [
            'author_url' => $value,
            'author_host' => $value === null ? null : Links::host($value),
        ]);
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CommentStatus::Approved);
    }

    /** Mentions somebody sent us, as opposed to ones read out of another page's thread. */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_source_url');
    }

    /**
     * The kind as an enum, or null for a value this app does not recognise.
     *
     * Read off the raw attributes: `$this->kind` would resolve this method as a
     * relationship on a row that has not been given one yet, and throw.
     */
    public function kind(): ?WebmentionKind
    {
        return WebmentionKind::tryFrom((string) ($this->attributes['kind'] ?? ''));
    }
}
