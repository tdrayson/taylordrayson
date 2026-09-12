<?php

namespace App\Models;

use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A response left on one of my posts somewhere else: a kudo on Strava, a like
 * on Swarm. Pulled by us rather than sent to us, which is what separates these
 * from webmentions.
 */
#[Fillable([
    'source',
    'source_id',
    'parent_source_id',
    'kind',
    'emoji',
    'author_name',
    'author_photo_path',
    'author_photo_url',
    'body',
    'url',
    'occurred_at',
    'timezone',
    'status',
])]
class SyndicatedResponse extends Model
{
    use HasFactory;

    /**
     * `kind` is cast where a webmention's is not: this vocabulary is ours,
     * normalised on the way in, so an unknown value is a bug rather than
     * somebody else's markup.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => WebmentionKind::class,
            'status' => CommentStatus::class,
            'body' => 'array',
            'occurred_at' => 'datetime',
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
}
