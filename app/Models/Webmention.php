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
    'target_url',
    'kind',
    'author_name',
    'author_url',
    'author_host',
    'author_photo_path',
    'author_photo_url',
    'content',
    'published_at',
    'status',
    'verified_at',
    'last_checked_at',
])]
class Webmention extends Model
{
    use HasFactory;

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

    /** The kind as an enum, or null for a value this app does not recognise. */
    public function kind(): ?WebmentionKind
    {
        return WebmentionKind::tryFrom((string) $this->kind);
    }
}
