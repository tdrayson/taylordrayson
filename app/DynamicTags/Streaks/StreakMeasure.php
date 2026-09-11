<?php

namespace App\DynamicTags\Streaks;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\Cadence;
use App\Enums\TimelineType;
use App\Queries\StreakDays;
use App\Timeline\TypeRegistry;

/**
 * Shared resolution for the current/longest streak tags. The two differ only
 * by which measure they read off {@see StreakDays}, supplied by the subclass.
 */
abstract class StreakMeasure extends DynamicTag
{
    /** 'current' for the run in progress, 'longest' for the best ever. */
    abstract protected function measure(): string;

    public function group(): string
    {
        return 'Streaks';
    }

    /**
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('type', 'Type', array_column(TimelineType::cases(), 'value'), TimelineType::Calorie->value),
            new TagOption('every', 'Every', array_column(Cadence::cases(), 'value'), Cadence::Day->value),
            $this->iconOption(),
        ];
    }

    public function supportsIcon(): bool
    {
        return true;
    }

    /**
     * The chosen type, so the client can draw that type's glyph; a streak
     * without one falls back to the plain flame {@see StreakBadge} uses.
     *
     * @param  array<string, string>  $options
     */
    public function iconPayload(mixed $value, array $options): mixed
    {
        return ['type' => $options['type'] ?? null];
    }

    /**
     * Null when the type is unrecognised; otherwise the count is never null.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?int
    {
        $model = TypeRegistry::find($options['type'] ?? TimelineType::Calorie->value)['model'] ?? null;

        if ($model === null) {
            return null;
        }

        $cadence = Cadence::tryFrom($options['every'] ?? '') ?? Cadence::Day;

        return app(StreakDays::class)()[$model][$cadence->value][$this->measure()] ?? 0;
    }
}
