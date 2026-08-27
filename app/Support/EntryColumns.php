<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Splits an entry's own columns into what can be returned with it and what has
 * to be asked for separately.
 *
 * One activity's track, altitude and speed come to about 36KB of JSON, which is
 * most of a context window spent on a single ride. Those are named rather than
 * returned, with their sizes, so whoever is reading decides whether it is worth
 * fetching one.
 */
final class EntryColumns
{
    /** Sampled series: many readings across the entry, never wanted by accident. */
    private const SERIES = ['track', 'altitude', 'speed', 'heart_rate', 'stages'];

    /**
     * Anything this large is treated as a series even when it is not named
     * above, so a column added later cannot quietly start filling responses.
     */
    private const LARGE = 4096;

    /** Already on the card, or meaningless outside the database. */
    private const HIDDEN = ['id', 'occurred_at', 'created_at', 'updated_at', 'source_id'];

    /**
     * The entry's own values, minus what the card already carried and minus the
     * series.
     *
     * @return array<string, mixed>
     */
    public function scalars(Model $model): array
    {
        $values = [];

        foreach ($model->getAttributes() as $column => $_) {
            if ($this->isHidden($column) || $this->isSeries($model, $column)) {
                continue;
            }

            $values[$column] = $model->getAttribute($column);
        }

        return $values;
    }

    /**
     * Which series this entry actually has, and how many bytes each would cost.
     *
     * @return array<string, int>
     */
    public function series(Model $model): array
    {
        $sizes = [];

        foreach (array_keys($model->getAttributes()) as $column) {
            if ($this->isHidden($column) || ! $this->isSeries($model, $column)) {
                continue;
            }

            $sizes[$column] = $this->size($model, $column);
        }

        return $sizes;
    }

    /** One series by name, or null when this entry has no such column. */
    public function value(Model $model, string $column): mixed
    {
        return $this->isSeries($model, $column) ? $model->getAttribute($column) : null;
    }

    private function isHidden(string $column): bool
    {
        return in_array($column, self::HIDDEN, true);
    }

    private function isSeries(Model $model, string $column): bool
    {
        if (! array_key_exists($column, $model->getAttributes())) {
            return false;
        }

        return in_array($column, self::SERIES, true) || $this->size($model, $column) > self::LARGE;
    }

    private function size(Model $model, string $column): int
    {
        $value = $model->getAttributes()[$column];

        return $value === null ? 0 : strlen((string) $value);
    }
}
