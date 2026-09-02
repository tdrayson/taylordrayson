<?php

namespace App\Http\Requests;

use App\Enums\PhotoTagRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Places a subject or camera credit on a photograph. Authentication is
 * enforced by the route's `auth` middleware, not here.
 */
class PhotoTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'role' => ['required', Rule::enum(PhotoTagRole::class)],
            // A subject needs a point in the frame; a camera credit carries none.
            'x' => ['required_if:role,subject', 'prohibited_if:role,camera', 'numeric', 'between:0,100'],
            'y' => ['required_if:role,subject', 'prohibited_if:role,camera', 'numeric', 'between:0,100'],
        ];
    }
}
