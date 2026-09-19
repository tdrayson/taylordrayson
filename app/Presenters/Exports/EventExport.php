<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Geometry;
use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\Event;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;

/**
 * An event as an export: what it was and where, with a span honouring
 * `ends_at` and `all_day` so .ics becomes available, plus a point geometry
 * when the venue was located.
 */
final class EventExport
{
    public function present(Event $model): ExportData
    {
        $card = CardPresenter::for($model);
        $address = collect([$model->venue_name, $model->city, $model->country])->filter()->implode(', ');

        return new ExportData(
            type: TimelineType::Event,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('event', 'Event', $model->name, $model->name),
                ExportField::maybe('venue', 'Venue', $model->venue_name, $model->venue_name),
                $this->location($model),
                ExportField::maybe('organiser', 'Organiser', $model->organiser, $model->organiser),
                ExportField::maybe('ends', 'Ends', $model->ends_at?->format('j F Y, H:i'), $model->ends_at?->toIso8601String()),
            ])),
            links: [
                ...array_values(array_filter([ExportLink::maybe('tickets', 'Tickets', 'More about this event', $this->url($model))])),
                ...CommonLinks::for($model),
            ],
            body: $model->description,
            aspects: array_filter([
                Geometry::class => $model->latitude === null ? null : Geometry::point((float) $model->latitude, (float) $model->longitude),
                Span::class => Span::between($model->occurred_at, $model->ends_at ?? $model->occurred_at, $model->timezone(), $address === '' ? null : $address, (bool) $model->all_day),
            ]),
        );
    }

    /**
     * The `url` column read raw: the model also has a `url()` method building
     * its own page address, so `$model->url` resolves as that relation lookup
     * and throws whenever the column was never set.
     */
    private function url(Event $model): ?string
    {
        return $model->getAttributes()['url'] ?? null;
    }

    private function location(Event $model): ?ExportField
    {
        $display = collect([$model->address, $model->city, $model->country])->filter()->implode(', ');

        if ($display === '') {
            return null;
        }

        return ExportField::make('location', 'Where', $display, [
            'lat' => $model->latitude === null ? null : (float) $model->latitude,
            'lng' => $model->longitude === null ? null : (float) $model->longitude,
            'address' => $display,
        ]);
    }
}
