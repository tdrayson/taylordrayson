<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class ExistsOnModel implements ValidationRule
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        private string $modelClass,
        private string $column,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->modelClass::query()->where($this->column, $value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
