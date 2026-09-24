<?php

namespace App\Http\Requests\Game;

use App\Support\ProfanityFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSnakeScoreRequest extends FormRequest
{
    /**
     * The board is 20×12, so a score can never exceed the number of cells.
     */
    public const MAX_SCORE = 240;

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
                    if (ProfanityFilter::blocksName((string) $value)) {
                        $fail('Please choose a different name.');
                    }
                },
            ],
            'score' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SCORE],
            'nonce' => ['required', 'string', 'uuid'],
            'player_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Add a name for the board.',
            'score.min' => 'Eat at least one dot before saving a score.',
        ];
    }
}
