<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A subject's fact list: label and value pairs the page prints and nothing
 * computes with. A car and a watch want different details, so these are typed
 * in rather than migrated.
 */
final readonly class SubjectMeta implements Arrayable, Castable, JsonSerializable
{
    /** @param list<array{label: string, value: string}> $facts */
    private function __construct(public array $facts) {}

    public static function fromArray(?array $rows): self
    {
        $facts = [];

        foreach ($rows ?? [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($label !== '' && $value !== '') {
                $facts[] = ['label' => $label, 'value' => $value];
            }
        }

        return new self($facts);
    }

    /** @return list<array{label: string, value: string}> */
    public function toArray(): array
    {
        return $this->facts;
    }

    /** @return list<array{label: string, value: string}> */
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
            public function get($model, string $key, $value, array $attributes): SubjectMeta
            {
                return SubjectMeta::fromArray(json_decode($value ?? '[]', true));
            }

            public function set($model, string $key, $value, array $attributes): array
            {
                $meta = $value instanceof SubjectMeta ? $value : SubjectMeta::fromArray($value);

                return [$key => json_encode($meta->toArray())];
            }
        };
    }
}
