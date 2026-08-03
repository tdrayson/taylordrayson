<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSetgraphWorkoutRequest extends FormRequest
{
    /**
     * `occurred_at` takes anything strtotime understands, including the ISO 8601
     * Shortcuts produces natively. Any offset it carries is read as wall-clock
     * time, so `timezone` still has to name the zone (an offset alone cannot:
     * +01:00 is Europe/London, Europe/Paris and Africa/Lagos alike). Omitted, it
     * falls back to the home timezone.
     *
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
