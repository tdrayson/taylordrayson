<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['sometimes', 'date'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Send the workout share text as `text`.',
        ];
    }
}
