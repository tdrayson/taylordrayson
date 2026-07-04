<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date', 'before_or_equal:to'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Apply the standard occurred_at window to any resource query.
     */
    public function applyTo(Builder $query): Builder
    {
        return $query
            ->when($this->date('from'), fn (Builder $q, $from) => $q->where('occurred_at', '>=', $from->startOfDay()))
            ->when($this->date('to'), fn (Builder $q, $to) => $q->where('occurred_at', '<=', $to->endOfDay()));
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}
