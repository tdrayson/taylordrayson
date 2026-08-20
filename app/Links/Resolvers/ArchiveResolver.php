<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Enums\TimelineType;
use App\Links\LinkResolver;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Str;

/**
 * A type archive, /activities or /flights. Counted directly rather than through
 * StatsForType, which builds a whole stats page payload a hover card cannot use.
 */
class ArchiveResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        $slug = ltrim($path, '/');

        foreach (TypeRegistry::all() as $type => $definition) {
            if ($definition['slug'] !== $slug) {
                continue;
            }

            $count = TimelineEntry::query()->where('timelineable_type', $definition['model'])->count();

            return LinkPreviewData::archive(
                url: $path,
                label: $definition['label'],
                type: $type,
                accent: TimelineType::from($type)->accent(),
                summary: number_format($count).' '.Str::plural($definition['noun'], $count),
            );
        }

        return null;
    }
}
