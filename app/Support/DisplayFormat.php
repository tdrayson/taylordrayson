<?php

namespace App\Support;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Visitor-facing times and dates in the formats they chose in settings. Takes
 * wall-clock values as given and never converts zones. resources/js/lib/dateFormat.js
 * mirrors every format here.
 */
final readonly class DisplayFormat
{
    public function __construct(
        public TimeFormat $time = TimeFormat::TwelveHour,
        public DateFormat $date = DateFormat::Short,
    ) {}

    /**
     * The formats this request's cookies ask for, falling back to the defaults.
     *
     * @param  Request  $request  Its `pref_*` cookies are untrusted.
     */
    public static function for(Request $request): self
    {
        $settings = Preferences::for($request)['settings'];

        return new self(
            TimeFormat::tryFrom($settings['timeFormat'] ?? '') ?? TimeFormat::TwelveHour,
            DateFormat::tryFrom($settings['dateFormat'] ?? '') ?? DateFormat::Short,
        );
    }

    /**
     * A clock reading: "3:15pm" or "15:15".
     *
     * @param  CarbonInterface  $at  Already in the zone it should read in.
     */
    public function time(CarbonInterface $at): string
    {
        return $at->format($this->time === TimeFormat::TwelveHour ? 'g:ia' : 'H:i');
    }

    /**
     * A calendar date, e.g. "Tue 22 Sep 2026", "22/09/2026" or "2026-09-22".
     *
     * @param  CarbonInterface  $date  The day to show.
     * @param  bool  $weekday  Lead the short format with the day name.
     * @param  bool  $year  Include the year, off for chart axes.
     */
    public function date(CarbonInterface $date, bool $weekday = true, bool $year = true): string
    {
        $pattern = match ($this->date) {
            DateFormat::Short => ($weekday ? 'D ' : '').'j M'.($year ? ' Y' : ''),
            DateFormat::Long => 'j F'.($year ? ' Y' : ''),
            DateFormat::DayMonthYear => 'd/m'.($year ? '/Y' : ''),
            DateFormat::MonthDayYear => 'm/d'.($year ? '/Y' : ''),
            DateFormat::Iso => ($year ? 'Y-' : '').'m-d',
        };

        return $date->format($pattern);
    }

    /**
     * A date and clock reading together: "Tue 22 Sep 2026, 3:15pm".
     *
     * @param  CarbonInterface  $at  Already in the zone it should read in.
     * @param  bool  $weekday  Lead the short format with the day name.
     */
    public function dateTime(CarbonInterface $at, bool $weekday = true): string
    {
        return $this->date($at, $weekday).', '.$this->time($at);
    }

    /**
     * A span of days, sharing its month and year where the format reads that
     * way: "2-4 Jun 2022", "2 Jun - 4 Jul 2022", "02/06/2022 - 04/06/2022".
     *
     * @param  CarbonInterface  $start  The first day.
     * @param  CarbonInterface  $end  The last day.
     */
    public function range(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return $this->date($start, weekday: false);
        }

        $last = $this->date($end, weekday: false);

        if ($this->date->isNumeric() || $start->year !== $end->year) {
            return $this->date($start, weekday: false).' - '.$last;
        }

        if ($start->month === $end->month) {
            return $start->format('j').'-'.$last;
        }

        return $this->date($start, weekday: false, year: false).' - '.$last;
    }
}
