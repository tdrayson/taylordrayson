<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSetgraphWorkoutRequest extends FormRequest
{
    /**
     * `occurred_at` is when the workout *finished*, not when it started, since the
     * share sheet carries no date and Setgraph is normally shared on the way out of
     * the gym. It takes anything strtotime understands, read as wall-clock time.
     * `timezone` must still name the zone, since an offset alone cannot (+01:00 is
     * London, Paris and Lagos alike); omitted, it falls back to home.
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
