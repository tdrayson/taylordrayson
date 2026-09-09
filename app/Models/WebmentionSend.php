<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per (our post, their URL): where we have sent, what came back, and
 * the body hash at the time. Together these are what let a failed send be
 * retried rather than mistaken for a delivered one.
 */
#[Fillable([
    'source_url',
    'target_url',
    'endpoint',
    'status',
    'status_code',
    'attempts',
    'content_hash',
    'last_sent_at',
])]
class WebmentionSend extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'status_code' => 'integer',
            'attempts' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
