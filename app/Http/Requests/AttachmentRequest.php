<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Set a photograph's alt text and caption. Authentication is enforced by the
 * route's `auth` middleware, not here.
 */
class AttachmentRequest extends FormRequest
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
            'alt' => ['nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
