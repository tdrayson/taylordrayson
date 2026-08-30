<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'required', 'string', 'max:'.Note::MAX_LENGTH],
            'occurred_at' => ['sometimes', 'date'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
