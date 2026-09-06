<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'author_name',
    'author_email',
    'notify_replies',
    'unsubscribed_at',
    'body',
    'status',
    'parent_id',
    'ip_hash',
    'user_agent',
])]
class Comment extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'array',
            'status' => CommentStatus::class,
            'notify_replies' => 'boolean',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Only approved comments are ever shown or counted. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CommentStatus::Approved);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CommentStatus::Pending);
    }

    /** Where this comment sits on its entry's page. */
    public function fragment(): string
    {
        return "comment-{$this->id}";
    }

    /**
     * Whether this address should be told about a reply: it must have been
     * given, opted in, and not since unsubscribed.
     */
    public function wantsReplyNotifications(): bool
    {
        return filled($this->author_email)
            && $this->notify_replies
            && $this->unsubscribed_at === null;
    }
}
