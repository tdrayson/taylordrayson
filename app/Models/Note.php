<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\LinkFaviconObserver;
use App\Observers\TimelineEntryObserver;
use App\Support\PortableText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([TimelineEntryObserver::class, LinkFaviconObserver::class])]
#[Fillable([
    'occurred_at',
    'content',
    'slug',
    'timezone',
])]
class Note extends Model implements HasMedia, Timelineable
{
    /** Where a note's own words run out, e.g. a note that is only a photo. */
    public const FALLBACK_SLUG = 'note';

    /**
     * How long a note may be, in characters of its readable text.
     *
     * Past this it is an article: the editor offers to convert rather than
     * refusing the words, so the limit shapes what a note is rather than
     * costing you what you wrote.
     */
    public const MAX_LENGTH = 750;

    /** How much of the note the derived slug uses. */
    private const SLUG_WORDS = 6;

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
        return $this->attributes['slug'] ?? self::slugFrom($this->attributes['content'] ?? null);
    }

    /**
     * The slug a note falls back to: the opening words of what it says.
     *
     * A note has no title to derive one from, and the alternative is every
     * note in a day sharing a bare "note" and separating only by a counter.
     *
     * The editor previews this as you type, so noteSlug() in
     * resources/js/lib/editor/defaults.js has to apply the same rule.
     */
    public static function slugFrom(array|string|null $content): string
    {
        $words = Str::words(PortableText::plainText($content), self::SLUG_WORDS, '');

        return Str::slug($words) ?: self::FALLBACK_SLUG;
    }
}
