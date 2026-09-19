<?php

namespace App\Presenters\Exports\Formats;

use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Enums\ExportFormat;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/** The entry as a calendar event. Available only with a Span aspect. */
final class IcsFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Ics;
    }

    public function supports(ExportData $data): bool
    {
        return ! $data->locked && $data->aspect(Span::class) !== null;
    }

    public function render(ExportData $data, array $trail): string
    {
        $span = $data->aspect(Span::class);

        $event = Event::create($data->title)
            ->startsAt($span->start)
            ->endsAt($span->end)
            ->url($data->url)
            ->uniqueIdentifier($data->url);

        if ($data->summary !== null) {
            $event->description($data->summary);
        }

        if ($span->location !== null) {
            $event->address($span->location);
        }

        if ($span->allDay) {
            $event->fullDay();
        }

        // Calendar::create() emits VTIMEZONE for every zone an event's start/end
        // carries by default; no method call needed to switch it on.
        return Calendar::create($data->title)
            ->event($event)
            ->get();
    }
}
