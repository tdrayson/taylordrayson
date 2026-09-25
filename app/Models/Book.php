<?php

namespace App\Models;

use App\Data\BookMeta;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasSpan;
use App\Models\Concerns\HasStatus;
use App\Models\Concerns\HasSubjects;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\BookProgress;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable(['occurred_at', 'started_at', 'title', 'rating', 'timezone', 'source', 'source_id', 'meta', 'status', 'password', 'progress_percent', 'current_page', 'pages', 'progressed_at', 'overview'])]
final class Book extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasSpan, HasStatus, HasSubjects, HasTags, HasTimelineEntry;

    protected $appends = ['percent_read'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'started_at' => 'datetime',
            'progressed_at' => 'datetime',
            'progress_percent' => 'float',
            'current_page' => 'integer',
            'pages' => 'integer',
            'meta' => BookMeta::class,
        ];
    }

    /**
     * Reading progress floored to a whole percent, the only form it is ever
     * shown in. Not persisted: `progress_percent` is what the sync and the
     * page-based calculation write.
     *
     * @return Attribute<int|null, never>
     */
    protected function percentRead(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->progress_percent === null ? null : BookProgress::display($this->progress_percent),
        );
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }
}
