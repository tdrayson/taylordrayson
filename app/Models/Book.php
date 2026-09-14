<?php

namespace App\Models;

use App\Data\BookMeta;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasSpan;
use App\Models\Concerns\HasStatus;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable(['occurred_at', 'started_at', 'title', 'overview', 'rating', 'timezone', 'source', 'source_id', 'meta', 'status', 'password'])]
final class Book extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasSpan, HasStatus, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'started_at' => 'datetime',
            'meta' => BookMeta::class,
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->title);
    }
}
