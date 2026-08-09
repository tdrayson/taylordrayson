<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use App\Support\PortableText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'content',
    'slug',
    'timezone',
])]
class Note extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Portable Text, same as pages and articles, so a note can carry inline
     * mentions. The column is still TEXT; this is what reads it.
     *
     * Assigning a plain string wraps it into a single block rather than
     * failing, because plenty of callers only have a string to give: Micropub,
     * a Shortcut, a CSV import, a factory. The editor assigns blocks directly.
     *
     * @return Attribute<array<int, mixed>, string>
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => PortableText::nodes($value),
            set: fn (array|string|null $value): string => json_encode(
                is_string($value) ? PortableText::fromPlainText($value) : ($value ?? []),
            ),
        );
    }

    /**
     * The author-set slug when given, read from the raw attribute so unsaved
     * models fall back cleanly under strict attribute access.
     */
    public function slug(): string
    {
        return $this->attributes['slug'] ?? 'note';
    }
}
