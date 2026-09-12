<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Checkin;
use App\Presenters\Cards\CheckinCard;
use App\Timeline\Taxonomies;
use Illuminate\Support\Str;

/**
 * Places checked in to, from Swarm.
 */
final class CheckinDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Checkin;
    }

    public function model(): string
    {
        return Checkin::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Travel;
    }

    public function icon(): string
    {
        return 'Location01Icon';
    }

    public function label(): string
    {
        return 'Place';
    }

    public function plural(): string
    {
        return 'Places';
    }

    public function slug(): string
    {
        return 'places';
    }

    public function keywords(): string
    {
        return 'place location visited';
    }

    // The only type whose OG eyebrow is its plural: a place card leads with its section.
    public function eyebrow(): ?string
    {
        return 'Places';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['check-in', 'check-ins'];
    }

    public function card(): CheckinCard
    {
        return new CheckinCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'venue' => ['label' => 'Venue', 'dataType' => 'text', 'column' => 'venue_name', 'category' => 'Place'],
            'category' => ['label' => 'Category', 'dataType' => 'enum', 'column' => 'category', 'category' => 'Place'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Place'],
            'city' => ['label' => 'City', 'dataType' => 'text', 'column' => 'city', 'category' => 'Location'],
            'county' => ['label' => 'County', 'dataType' => 'text', 'column' => 'county', 'category' => 'Location'],
            'country' => ['label' => 'Country', 'dataType' => 'enum', 'column' => 'country', 'category' => 'Location'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['venue_name', 'category', 'city', 'description'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::column('category', 'Category', fn (string $label): string => Str::plural($label));
    }
}
