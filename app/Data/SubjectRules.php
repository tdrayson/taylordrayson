<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A subject's auto-tag rules: entry type, column and value to match on sync.
 * Not read by PR 1 beyond storage.
 */
final readonly class SubjectRules implements Arrayable, Castable, JsonSerializable
{
    /** @param list<array{type: string, column: string, value: string}> $rules */
    private function __construct(public array $rules) {}

    public static function fromArray(?array $rows): self
    {
        $rules = [];

        foreach ($rows ?? [] as $row) {
            $type = trim((string) ($row['type'] ?? ''));
            $column = trim((string) ($row['column'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($type !== '' && $column !== '' && $value !== '') {
                $rules[] = ['type' => $type, 'column' => $column, 'value' => $value];
            }
        }

        return new self($rules);
    }

    /** @return list<array{type: string, column: string, value: string}> */
    public function forType(string $type): array
    {
        return array_values(array_filter(
            $this->rules,
            fn (array $rule): bool => $rule['type'] === $type,
        ));
    }

    /** @return list<array{type: string, column: string, value: string}> */
    public function toArray(): array
    {
        return $this->rules;
    }

    /** @return list<array{type: string, column: string, value: string}> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): SubjectRules
            {
                return SubjectRules::fromArray(json_decode($value ?? '[]', true));
            }

            public function set($model, string $key, $value, array $attributes): array
            {
                $rules = $value instanceof SubjectRules ? $value : SubjectRules::fromArray($value);

                return [$key => json_encode($rules->toArray())];
            }
        };
    }
}
