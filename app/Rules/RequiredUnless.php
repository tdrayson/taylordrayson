<?php

namespace App\Rules;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Required unless every named field holds one of its given values, reading a
 * field from the stored model when a partial update does not send it.
 */
class RequiredUnless implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<string, list<string>>  $conditions  Field name to the values that excuse this one.
     * @param  Model|null  $stored  The row being updated, or null on create.
     */
    public function __construct(private array $conditions, private ?Model $stored = null) {}

    /**
     * @param  array<string, mixed>  $data
     * @return $this
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $excused = array_all(
            $this->conditions,
            fn (array $values, string $name): bool => in_array($this->valueOf($name), $values, strict: true),
        );

        if (! $excused && ($value === null || $value === [] || (is_string($value) && trim($value) === ''))) {
            $fail('The :attribute field is required.');
        }
    }

    private function valueOf(string $name): mixed
    {
        $value = Arr::has($this->data, $name) ? Arr::get($this->data, $name) : $this->stored?->getAttribute($name);

        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
