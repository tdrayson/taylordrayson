<?php

namespace App\Http\Requests;

use App\Enums\ReviewKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marks a photograph reviewed for a dimension, or puts it back.
 * Authentication is enforced by the route's `auth` middleware, not here.
 */
class PhotoReviewRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(ReviewKind::class)],
        ];
    }
}
