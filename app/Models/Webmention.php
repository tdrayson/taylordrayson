<?php

namespace App\Models;

use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'source_url',
    'target_url',
    'kind',
    'author_name',
    'author_url',
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
            'status' => CommentStatus::class,
            'published_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
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
