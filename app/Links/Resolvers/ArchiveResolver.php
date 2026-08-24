<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Enums\TimelineType;
use App\Links\LinkResolver;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A type archive, /activities or /flights. Counted directly rather than through
 * StatsForType, which builds a whole stats page payload a hover card cannot use.
 */
class ArchiveResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        $slug = trim($path, '/');

        foreach (TypeRegistry::all() as $type => $definition) {
            if ($definition['slug'] === $slug) {
                $count = TimelineEntry::query()->where('timelineable_type', $definition['model'])->count();

                return $this->preview($path, $definition['label'], $type, $definition['noun'], $count);
            }

            $taxonomy = $definition['taxonomy'] ?? null;

            if ($taxonomy === null || ! str_starts_with($slug, $taxonomy['base'].'/')) {
                continue;
            }

            $value = substr($slug, strlen($taxonomy['base']) + 1);

            if ($value === '' || str_contains($value, '/')) {
                continue;
            }

            return $this->taxonomy($path, $type, $definition, $taxonomy, $value);
        }

        return null;
    }

    /**
     * One value within a type's taxonomy: /activities/run, /flights/easyjet.
     *
     * The value is checked against the taxonomy's own list rather than trusted,
     * because a literal route sits at the same shape: without this, /flights/map
     * would preview as an airline nobody has ever flown.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $taxonomy
     */
    private function taxonomy(string $path, string $type, array $definition, array $taxonomy, string $value): ?LinkPreviewData
    {
        if (! $taxonomy['values']()->pluck('value')->contains($value)) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];

        $count = $model::query()
            ->tap(fn ($query) => ($taxonomy['filter'])($query, $value))
            ->count();

        return $this->preview($path, ($taxonomy['labelFor'])($value), $type, $definition['noun'], $count);
    }

    private function preview(string $path, string $label, string $type, string $noun, int $count): LinkPreviewData
    {
        return LinkPreviewData::archive(
            url: $path,
            label: $label,
            type: $type,
            accent: TimelineType::from($type)->accent(),
            summary: number_format($count).' '.Str::plural($noun, $count),
        );
    }
}
