<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\SpanAnchor;
use App\Enums\TimelineType;
use App\Models\Event;
use App\Presenters\Cards\EventCard;
use App\Timeline\Taxonomies;

/**
 * Gigs, concerts and other ticketed events attended.
 */
final class EventDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Event;
    }

    public function model(): string
    {
        return Event::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::GoingOut;
    }

    public function icon(): string
    {
        return 'Ticket01Icon';
    }

    public function label(): string
    {
        return 'Event';
    }

    public function plural(): string
    {
        return 'Events';
    }

    public function slug(): string
    {
        return 'events';
    }

    public function keywords(): string
    {
        return 'ticket gig concert';
    }

    public function card(): EventCard
    {
        return new EventCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'name' => ['label' => 'Name', 'dataType' => 'text', 'column' => 'name', 'category' => 'Event'],
            'description' => ['label' => 'Description', 'dataType' => 'text', 'column' => 'description', 'category' => 'Event'],
            'organiser' => ['label' => 'Organiser', 'dataType' => 'text', 'column' => 'organiser', 'category' => 'Event'],
            'venue' => ['label' => 'Venue', 'dataType' => 'text', 'column' => 'venue_name', 'category' => 'Location'],
            'city' => ['label' => 'City', 'dataType' => 'text', 'column' => 'city', 'category' => 'Location'],
            'country' => ['label' => 'Country', 'dataType' => 'text', 'column' => 'country', 'category' => 'Location'],
            'photos' => ['label' => 'Photos', 'dataType' => 'media', 'column' => null, 'category' => 'Media', 'suffix' => 'photos'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['name', 'venue_name', 'city', 'country'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::tags(fn (string $label): string => "{$label} events");
    }

    public function spanAnchor(): SpanAnchor
    {
        return SpanAnchor::Start;
    }

    public function draftable(): bool
    {
        return true;
    }
}
