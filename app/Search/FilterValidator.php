<?php

namespace App\Search;

/**
 * Decodes and validates a URL-encoded advanced-search filter against the
 * SearchSchema, dropping any unknown type, field, or operator so only safe,
 * known clauses ever reach the SearchCompiler.
 */
final class FilterValidator
{
    /**
     * Decode and whitelist the URL-encoded filter against the schema, dropping any
     * unknown type, field or operator so only safe, known clauses reach the compiler.
     *
     * @param  mixed  $raw  The raw `filter` query value (expected to be a JSON string).
     * @return array<int, array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}>
     */
    public function __invoke(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $schema = SearchSchema::types();

        return collect($decoded)
            ->map(fn (mixed $group): ?array => $this->validateGroup($schema, $group))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Validate one group, returning null when its type is unknown or it has no
     * surviving conditions.
     *
     * @param  array<string, mixed>  $schema  The full schema keyed by type.
     * @param  mixed  $group  A candidate group from the decoded filter.
     * @return array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}|null
     */
    private function validateGroup(array $schema, mixed $group): ?array
    {
        $type = is_array($group) ? ($group['type'] ?? null) : null;

        if (! isset($schema[$type]) || ! isset($group['conditions']) || ! is_array($group['conditions'])) {
            return null;
        }

        $conditions = $this->validateConditions($schema[$type]['fields'], $group['conditions']);

        return $conditions === [] ? null : ['type' => $type, 'conditions' => $conditions];
    }

    /**
     * Keep only conditions whose field exists and whose operator is allowed for it.
     *
     * @param  array<string, array<string, mixed>>  $fields  The type's field definitions.
     * @param  array<int, mixed>  $conditions  Candidate conditions from the filter.
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    private function validateConditions(array $fields, array $conditions): array
    {
        return collect($conditions)
            ->filter(fn (mixed $condition): bool => is_array($condition))
            ->map(function (array $condition) use ($fields): ?array {
                $field = $fields[$condition['field'] ?? null] ?? null;
                $operator = $condition['operator'] ?? null;

                if ($field === null || ! in_array($operator, $field['operators'], true)) {
                    return null;
                }

                return [
                    'field' => $condition['field'],
                    'operator' => $operator,
                    'value' => $condition['value'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
