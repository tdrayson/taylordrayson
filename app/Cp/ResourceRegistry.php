<?php

namespace App\Cp;

class ResourceRegistry
{
    /** @var array<int, class-string<CpResource>> */
    private const RESOURCES = [
        Resources\ActivityResource::class,
        Resources\SleepResource::class,
        Resources\CalorieResource::class,
        Resources\MediaResource::class,
        Resources\EventResource::class,
        Resources\AppearanceResource::class,
        Resources\PodcastResource::class,
        Resources\FlightResource::class,
        Resources\CheckinResource::class,
        Resources\FuelResource::class,
        Resources\ProjectResource::class,
        Resources\ArticleResource::class,
        Resources\NoteResource::class,
        Resources\AirlineResource::class,
        Resources\AirportResource::class,
        Resources\FuelStationResource::class,
    ];

    /**
     * @return array<string, CpResource>
     */
    public function all(): array
    {
        $resources = [];

        foreach (self::RESOURCES as $class) {
            $resource = new $class;
            $resources[$resource->slug()] = $resource;
        }

        return $resources;
    }

    public function find(string $slug): ?CpResource
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * Grouped navigation for the control panel sidebar.
     *
     * @return array<int, array{group: string, items: array<int, array{label: string, slug: string}>}>
     */
    public function nav(): array
    {
        $grouped = [];

        foreach ($this->all() as $resource) {
            $grouped[$resource->group()][] = ['label' => $resource->pluralLabel(), 'slug' => $resource->slug()];
        }

        return collect($grouped)
            ->map(fn (array $items, string $group): array => ['group' => $group, 'items' => $items])
            ->values()
            ->all();
    }
}
