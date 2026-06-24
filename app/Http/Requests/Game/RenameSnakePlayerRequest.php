<?php

namespace App\Http\Requests\Game;

use App\Support\ProfanityFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RenameSnakePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim and de-tag the submitted name before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(strip_tags((string) $this->input('name'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (ProfanityFilter::contains((string) $value)) {
                        $fail('Please choose a different name.');
                    }
                },
            ],
            'player_id' => ['required', 'string', 'uuid'],
        ];
    }
}
