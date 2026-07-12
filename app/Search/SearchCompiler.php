<?php

namespace App\Search;

use App\Models\Airline;
use App\Models\Article;
use App\Support\Distance;
use App\Timeline\TypeRegistry;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Compiles a validated filter (groups OR-ed, each holding AND-ed conditions over
 * a single timeline type) into a TimelineEntry query.
 *
 * Filter shape: array<int, array{type: string, conditions: array<int, array{
 *     field: string, operator: string, value: mixed
 * }>}>
 */
class SearchCompiler
{
    /**
     * Constrain the given TimelineEntry query with the filter. Groups are OR-ed
     * together; the conditions inside each group are AND-ed.
     *
     * @param  EloquentBuilder  $query  The base TimelineEntry query to constrain.
     * @param  array<int, array<string, mixed>>  $groups  Validated filter groups.
     * @return EloquentBuilder The same query instance, with the filter applied.
     */
    public function apply(EloquentBuilder $query, array $groups): EloquentBuilder
    {
        $schema = SearchSchema::types();

        $query->where(function (EloquentBuilder $outer) use ($groups, $schema): void {
            $isFirstGroup = true;

            foreach ($groups as $group) {
                $type = $schema[$group['type']] ?? null;

                if ($type === null) {
                    continue;
                }

                $this->applyGroup($outer, $group, $type, $isFirstGroup);
                $isFirstGroup = false;
            }
        });

        return $query;
    }

    /**
     * Add a single group to the outer query, OR-ing it onto any previous group.
     *
     * @param  EloquentBuilder  $outer  The grouping closure's query.
     * @param  array<string, mixed>  $group  The group: { type, conditions }.
     * @param  array<string, mixed>  $type  The group type's schema definition.
     * @param  bool  $isFirstGroup  Whether this seeds the chain (AND) or OR-s on.
     */
    private function applyGroup(EloquentBuilder $outer, array $group, array $type, bool $isFirstGroup): void
    {
        if ($group['type'] === 'any') {
            $method = $isFirstGroup ? 'where' : 'orWhere';
            $outer->{$method}(fn (Builder $any) => $this->applyAnyGroup($any, $group, $type));

            return;
        }

        $method = $isFirstGroup ? 'whereHasMorph' : 'orWhereHasMorph';
        $outer->{$method}('timelineable', [$type['model']], function (Builder $morph) use ($group, $type): void {
            $this->guardPublished($morph, $type['model']);
            $this->applyConditions($morph, $group, $type);
        });
    }

    /**
     * Defence in depth against a stale timeline_entries row (e.g. a mass update
     * that bypassed model observers): guests never see an unpublished article
     * in search results. Public so other search entry points (e.g. the command
     * palette's free-text suggest endpoint) share this single gate rather than
     * duplicating the guard logic.
     *
     * @param  Builder  $query  The (possibly morphed) model query to constrain.
     * @param  class-string|null  $model  The model class this query targets.
     */
    public function guardPublished(Builder $query, ?string $model): void
    {
        if ($model === Article::class && ! Auth::check()) {
            $query->where('published', true);
        }
    }

    /**
     * Apply every known condition in a typed group to its morphed model query.
     *
     * @param  Builder  $query  The morphed model query for the group's type.
     * @param  array<string, mixed>  $group  The group: { type, conditions }.
     * @param  array<string, mixed>  $type  The group type's schema definition.
     */
    private function applyConditions(Builder $query, array $group, array $type): void
    {
        foreach ($group['conditions'] as $condition) {
            $field = $type['fields'][$condition['field']] ?? null;

            if ($field === null) {
                continue;
            }

            $this->applyCondition($query, $field, $condition['operator'], $condition['value']);
        }
    }

    /**
     * Apply the generic "Anything" group: a date filters the entry directly, free
     * text matches across every type's text columns.
     *
     * @param  Builder  $query  The TimelineEntry sub-query for this group.
     * @param  array<string, mixed>  $group  The group: { type, conditions }.
     * @param  array<string, mixed>  $type  The "any" schema definition.
     */
    private function applyAnyGroup(Builder $query, array $group, array $type): void
    {
        foreach ($group['conditions'] as $condition) {
            $field = $type['fields'][$condition['field']] ?? null;

            if ($field === null) {
                continue;
            }

            if ($field['key'] === 'text') {
                $this->anyText($query, (string) $condition['value']);

                continue;
            }

            if ($field['dataType'] === 'media') {
                $this->anyMedia($query, $condition['operator'], $condition['value']);

                continue;
            }

            // The remaining "any" fields are date presets that constrain the entry directly.
            $this->clause($query, $field['column'], $field['dataType'], $condition['operator'], $condition['value']);
        }
    }

    /**
     * Match free text against every type's text columns via the polymorphic relation.
     *
     * @param  Builder  $query  The TimelineEntry query to constrain.
     * @param  string  $value  The search term.
     */
    private function anyText(Builder $query, string $value): void
    {
        $registry = TypeRegistry::all();
        $models = collect(SearchSchema::TEXT_COLUMNS)
            ->keys()
            ->map(fn (string $key): string => $registry[$key]['model'])
            ->all();

        $query->whereHasMorph('timelineable', $models, function (Builder $morph, string $modelClass) use ($registry, $value): void {
            $this->guardPublished($morph, $modelClass);

            $key = collect($registry)->search(fn (array $definition): bool => $definition['model'] === $modelClass);
            $columns = SearchSchema::TEXT_COLUMNS[$key] ?? [];

            $morph->where(function (Builder $inner) use ($columns, $value): void {
                foreach ($columns as $column) {
                    $inner->orWhere($column, 'like', "%{$value}%");
                }
            });
        });
    }

    /**
     * Constrain a model query by its photo count (the cover + photos collections),
     * comparing against the operator and value. Used by typed and Anything groups.
     *
     * @param  Builder  $query  The (possibly morphed) model query to constrain.
     * @param  string  $operator  A numeric operator (eq/neq/gt/gte/lt/lte/between).
     * @param  mixed  $value  The photo count, or a [min, max] pair for "between".
     */
    private function mediaClause(Builder $query, string $operator, mixed $value): void
    {
        $photos = fn (Builder $media) => $media->whereIn('collection_name', ['cover', 'photos']);

        if ($operator === 'has_any') {
            $query->whereHas('media', $photos);

            return;
        }

        if ($operator === 'has_none') {
            $query->whereDoesntHave('media', $photos);

            return;
        }

        if ($operator === 'between') {
            $range = $this->numberRange($value);

            if ($range === null) {
                return;
            }

            $query->whereHas('media', $photos, '>=', (int) $range[0])
                ->whereHas('media', $photos, '<=', (int) $range[1]);

            return;
        }

        $comparators = ['eq' => '=', 'neq' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

        if (! isset($comparators[$operator])) {
            return;
        }

        $query->whereHas('media', $photos, $comparators[$operator], (int) $value);
    }

    /**
     * Apply a photo-count filter across every timeline type for the Anything group.
     *
     * @param  Builder  $query  The TimelineEntry query to constrain.
     * @param  string  $operator  A numeric operator.
     * @param  mixed  $value  The photo count, or a [min, max] pair for "between".
     */
    private function anyMedia(Builder $query, string $operator, mixed $value): void
    {
        $models = collect(TypeRegistry::all())->pluck('model')->all();

        $query->whereHasMorph('timelineable', $models, function (Builder $morph, string $modelClass) use ($operator, $value): void {
            $this->guardPublished($morph, $modelClass);
            $this->mediaClause($morph, $operator, $value);
        });
    }

    /**
     * Apply one condition, delegating relation fields through a whereHas.
     *
     * @param  Builder  $query  The model query to constrain.
     * @param  array<string, mixed>  $field  The field definition (column, dataType, relation).
     * @param  string  $operator  The whitelisted operator.
     * @param  mixed  $value  The filter value.
     */
    private function applyCondition(Builder $query, array $field, string $operator, mixed $value): void
    {
        if (! in_array($operator, SearchSchema::operatorsFor($field['dataType']), true)) {
            return;
        }

        if ($field['dataType'] === 'media') {
            $this->mediaClause($query, $operator, $value);

            return;
        }

        if (isset($field['relation'])) {
            if ($field['relation'] === 'airline') {
                $this->lookupRelationClause(
                    $query,
                    Airline::class,
                    'airline_icao',
                    'icao_code',
                    $field,
                    $operator,
                    $value,
                );

                return;
            }

            $query->whereHas(
                $field['relation'],
                fn (Builder $related) => $this->clause($related, $field['column'], $field['dataType'], $operator, $value, $field['unit'] ?? null)
            );

            return;
        }

        $this->clause($query, $field['column'], $field['dataType'], $operator, $value, $field['unit'] ?? null);
    }

    /**
     * Resolve a Sushi/cross-connection relation filter by matching related rows
     * first, then constraining the local foreign key (whereHas cannot join).
     *
     * @param  class-string<Model>  $relatedClass
     * @param  array<string, mixed>  $field
     */
    private function lookupRelationClause(
        Builder $query,
        string $relatedClass,
        string $localKey,
        string $relatedKey,
        array $field,
        string $operator,
        mixed $value,
    ): void {
        $related = $relatedClass::query();
        $this->clause($related, $field['column'], $field['dataType'], $operator, $value, $field['unit'] ?? null);

        $keys = $related->pluck($relatedKey)->filter()->values()->all();

        $query->whereIn($localKey, $keys !== [] ? $keys : ['__none__']);
    }

    /**
     * Route a single column comparison to the handler for its data type.
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The column to compare.
     * @param  string  $dataType  One of text|enum|number|date.
     * @param  string  $operator  The whitelisted operator.
     * @param  mixed  $value  The filter value.
     * @param  string|null  $unit  The schema's display unit (e.g. km/mi) for a number field, used to
     *                             scale the human-entered value to the column's storage unit.
     */
    private function clause(Builder $query, string $column, string $dataType, string $operator, mixed $value, ?string $unit = null): void
    {
        match ($dataType) {
            'text' => $this->textClause($query, $column, $operator, (string) $value),
            'enum' => $this->enumClause($query, $column, $operator, $value),
            'number', 'duration' => $this->numberClause($query, $column, $operator, $value, $unit),
            'day' => $this->dayClause($query, $column, $operator, $value),
            'month' => $this->periodClause($query, $column, $operator, $value, fn (string $bound): ?array => $this->monthBounds($bound)),
            'year' => $this->periodClause($query, $column, $operator, $value, fn (string $bound): ?array => $this->yearBounds($bound)),
            default => null,
        };
    }

    /**
     * Apply a text comparison (contains / not contains / equals / starts with).
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The text column.
     * @param  string  $operator  The text operator.
     * @param  string  $value  The search term.
     */
    private function textClause(Builder $query, string $column, string $operator, string $value): void
    {
        match ($operator) {
            'contains' => $query->where($column, 'like', "%{$value}%"),
            'not_contains' => $query->where($column, 'not like', "%{$value}%"),
            'equals' => $query->where($column, '=', $value),
            'starts_with' => $query->where($column, 'like', "{$value}%"),
            'ends_with' => $query->where($column, 'like', "%{$value}"),
            default => null,
        };
    }

    /**
     * Apply an enum comparison. `is` / `is not` match any of the selected values
     * (whereIn / whereNotIn); the text operators fall through to a string match.
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The enum column.
     * @param  string  $operator  The enum operator.
     * @param  mixed  $value  A list of values for is/is_not, otherwise a string.
     */
    private function enumClause(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($operator !== 'is' && $operator !== 'is_not') {
            $this->textClause($query, $column, $operator, (string) $value);

            return;
        }

        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            fn (mixed $item): bool => $item !== '' && $item !== null,
        ));

        if ($values === []) {
            return;
        }

        $operator === 'is_not' ? $query->whereNotIn($column, $values) : $query->whereIn($column, $values);
    }

    /**
     * Apply a numeric comparison (equality, ordering, or a range). When the field
     * declares a display unit (km/mi), the human-entered value(s) are scaled to the
     * column's storage unit (integer metres) before the comparison is built.
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The numeric column.
     * @param  string  $operator  The number operator.
     * @param  mixed  $value  A scalar, or a [min, max] array for "between".
     * @param  string|null  $unit  The schema's display unit (km/mi), or null for no scaling.
     */
    private function numberClause(Builder $query, string $column, string $operator, mixed $value, ?string $unit = null): void
    {
        $value = $this->scaleToStorageUnit($value, $unit);

        if ($operator === 'between' || $operator === 'not_between') {
            $this->applyBetween($query, $column, $this->numberRange($value), $operator === 'not_between');

            return;
        }

        $comparators = ['eq' => '=', 'neq' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

        if (isset($comparators[$operator])) {
            $query->where($column, $comparators[$operator], $value);
        }
    }

    /**
     * Scale a human-entered value (or [min, max] pair) from its display unit to the
     * column's storage unit (integer metres), leaving it untouched when the field
     * carries no unit, or the value is missing.
     *
     * @param  mixed  $value  A scalar, or a [min, max] array for "between".
     * @param  string|null  $unit  The schema's display unit (km/mi), or null for no scaling.
     */
    private function scaleToStorageUnit(mixed $value, ?string $unit): mixed
    {
        if ($unit === null) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->scaleToStorageUnit($item, $unit), $value);
        }

        if ($value === null || $value === '') {
            return $value;
        }

        return match ($unit) {
            'km' => Distance::fromKm((float) $value),
            'mi' => Distance::fromMiles((float) $value),
            default => $value,
        };
    }

    /**
     * Order a [a, b] pair numerically so a reversed range still resolves correctly.
     *
     * @param  mixed  $value  Expected to be a [a, b] pair.
     * @return array{0: mixed, 1: mixed}|null The ascending pair, or null when malformed.
     */
    private function numberRange(mixed $value): ?array
    {
        if (! is_array($value) || count($value) !== 2) {
            return null;
        }

        $pair = [$value[0], $value[1]];
        usort($pair, fn (mixed $low, mixed $high): int => $low <=> $high);

        return $pair;
    }

    /**
     * Apply a whereBetween (or whereNotBetween) for a resolved, ordered range.
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The column.
     * @param  array{0: mixed, 1: mixed}|null  $range  The ordered [low, high] range, or null to skip.
     * @param  bool  $negate  True to exclude the range (not between).
     */
    private function applyBetween(Builder $query, string $column, ?array $range, bool $negate): void
    {
        if ($range === null) {
            return;
        }

        $negate ? $query->whereNotBetween($column, $range) : $query->whereBetween($column, $range);
    }

    /**
     * Apply a day comparison (on / before / after / between), comparing date parts.
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The date column.
     * @param  string  $operator  The day operator.
     * @param  mixed  $value  A "YYYY-MM-DD" string, or a [from, to] pair for "between".
     */
    private function dayClause(Builder $query, string $column, string $operator, mixed $value): void
    {
        match ($operator) {
            'on' => $query->whereDate($column, '=', $value),
            'not_on' => $query->whereDate($column, '!=', $value),
            'before' => $query->whereDate($column, '<', $value),
            'after' => $query->whereDate($column, '>', $value),
            'between' => $this->applyBetween($query, $column, $this->dayRange($value), false),
            'not_between' => $this->applyBetween($query, $column, $this->dayRange($value), true),
            default => null,
        };
    }

    /**
     * Order a [from, to] day pair and widen it to cover whole days, inclusive.
     *
     * @param  mixed  $value  Expected to be a [from, to] pair of "YYYY-MM-DD" strings.
     * @return array{0: string, 1: string}|null The day range, or null when malformed.
     */
    private function dayRange(mixed $value): ?array
    {
        if (! is_array($value) || count($value) !== 2) {
            return null;
        }

        $days = [substr((string) $value[0], 0, 10), substr((string) $value[1], 0, 10)];
        sort($days);

        return ["{$days[0]} 00:00:00", "{$days[1]} 23:59:59"];
    }

    /**
     * Apply a month/year comparison by resolving the value(s) to calendar bounds.
     *
     * @param  Builder  $query  The query to constrain.
     * @param  string  $column  The date column.
     * @param  string  $operator  One of is (`in`) / is not (`not_in`) / before / after / between.
     * @param  mixed  $value  A period string, or a [from, to] pair for "between".
     * @param  callable(string): ?array{0: Carbon, 1: Carbon}  $bounds  Resolves a period to [start, end].
     */
    private function periodClause(Builder $query, string $column, string $operator, mixed $value, callable $bounds): void
    {
        if ($operator === 'between' || $operator === 'not_between') {
            $this->applyBetween($query, $column, $this->periodRange($value, $bounds), $operator === 'not_between');

            return;
        }

        $resolved = $bounds((string) $value);

        if ($resolved === null) {
            return;
        }

        match ($operator) {
            'in' => $query->whereBetween($column, $resolved),
            'not_in' => $query->whereNotBetween($column, $resolved),
            'before' => $query->where($column, '<', $resolved[0]),
            'after' => $query->where($column, '>', $resolved[1]),
            default => null,
        };
    }

    /**
     * Resolve a [from, to] period pair to an ordered [start, end] span (earliest
     * start to latest end), so a reversed range still resolves correctly.
     *
     * @param  mixed  $value  A [from, to] pair of period strings.
     * @param  callable(string): ?array{0: Carbon, 1: Carbon}  $bounds  Resolves a period to [start, end].
     * @return array{0: Carbon, 1: Carbon}|null The span, or null when malformed.
     */
    private function periodRange(mixed $value, callable $bounds): ?array
    {
        if (! is_array($value) || count($value) !== 2) {
            return null;
        }

        $from = $bounds((string) $value[0]);
        $to = $bounds((string) $value[1]);

        if ($from === null || $to === null) {
            return null;
        }

        $start = $from[0]->lte($to[0]) ? $from[0] : $to[0];
        $end = $from[1]->gte($to[1]) ? $from[1] : $to[1];

        return [$start, $end];
    }

    /**
     * Resolve a "YYYY-MM" month to its [start, end] Carbon bounds.
     *
     * @param  string  $value  The month as "YYYY-MM".
     * @return array{0: Carbon, 1: Carbon}|null Bounds, or null when malformed.
     */
    private function monthBounds(string $value): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$value}-01")->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * Resolve a "YYYY" year to its [start, end] Carbon bounds.
     *
     * @param  string  $value  The year as "YYYY".
     * @return array{0: Carbon, 1: Carbon}|null Bounds, or null when malformed.
     */
    private function yearBounds(string $value): ?array
    {
        if (! preg_match('/^\d{4}$/', $value)) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$value}-01-01")->startOfYear();

        return [$start, $start->copy()->endOfYear()];
    }
}
