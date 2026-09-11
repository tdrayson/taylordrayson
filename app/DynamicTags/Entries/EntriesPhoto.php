<?php

namespace App\DynamicTags\Entries;

use App\DynamicTags\DynamicTag;
use App\Enums\Placement;
use App\Queries\PhotoStream;

/** The most recent photo on the timeline. */
class EntriesPhoto extends DynamicTag
{
    public function name(): string
    {
        return 'entries.photo';
    }

    public function label(): string
    {
        return 'Latest photo';
    }

    public function group(): string
    {
        return 'Entries';
    }

    /**
     * Legal only as an image source, unlike most tags which default to inline.
     *
     * @return list<Placement>
     */
    public function supports(): array
    {
        return [Placement::Image];
    }

    /**
     * Ignores `$options`; the tag takes none. Null when the timeline has no photos.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?string
    {
        return app(PhotoStream::class)(1)[0]['full'] ?? null;
    }
}
