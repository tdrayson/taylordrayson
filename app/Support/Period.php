<?php

namespace App\Support;

use App\Enums\StatsPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * A date window from tag options: a named preset, a bare year, or an explicit
 * from/to, which wins over period. A parameter object only, shared with stats.
 */
final readonly class Period
{
    private function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
    ) {}

    /**
     * `from`/`to` win over `period`; a bare four-digit `period` is read as a year.
     *
     * @param  array<string, string>  $options
     */
    public static function from(array $options): self
    {
        if (isset($options['from'], $options['to'])) {
            return new self(
                self::parse($options['from'])?->startOfDay(),
                self::parse($options['to'])?->endOfDay(),
            );
        }

        if (isset($options['from'])) {
            return new self(self::parse($options['from'])?->startOfDay(), null);
        }

        if (isset($options['to'])) {
            return new self(null, self::parse($options['to'])?->endOfDay());
        }

        $period = $options['period'] ?? StatsPeriod::AllTime->value;

        if (preg_match('/^\d{4}$/', $period) === 1) {
            $year = CarbonImmutable::create((int) $period, 1, 1);

            return new self($year->startOfYear(), $year->endOfYear());
        }

        return self::preset(StatsPeriod::tryFrom($period) ?? StatsPeriod::AllTime);
    }

    /** An unparseable bound degrades to null, the same open-ended state a lone bound already means. */
    private static function parse(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function preset(StatsPeriod $preset): self
    {
        $now = CarbonImmutable::now();

        return match ($preset) {
            StatsPeriod::AllTime => new self(null, null),
            StatsPeriod::ThisYear => new self($now->startOfYear(), $now->endOfYear()),
            StatsPeriod::LastYear => new self($now->subYear()->startOfYear(), $now->subYear()->endOfYear()),
            StatsPeriod::ThisMonth => new self($now->startOfMonth(), $now->endOfMonth()),
            StatsPeriod::LastMonth => new self($now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()),
            StatsPeriod::Last7Days => new self($now->subDays(7)->startOfDay(), $now->endOfDay()),
            StatsPeriod::Last30Days => new self($now->subDays(30)->startOfDay(), $now->endOfDay()),
            StatsPeriod::Last90Days => new self($now->subDays(90)->startOfDay(), $now->endOfDay()),
            StatsPeriod::Last12Months => new self($now->subMonths(12)->startOfDay(), $now->endOfDay()),
        };
    }

    public function apply(Builder $query, string $column = 'occurred_at'): Builder
    {
        return $query
            ->when($this->start, fn (Builder $q) => $q->where($column, '>=', $this->start))
            ->when($this->end, fn (Builder $q) => $q->where($column, '<=', $this->end));
    }
}
