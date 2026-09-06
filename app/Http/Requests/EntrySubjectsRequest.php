<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole set of subjects to tag onto an entry. Authentication is enforced
 * by the route's `auth` middleware, not here.
 */
class EntrySubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subjects' => ['present', 'array'],
            'subjects.*' => ['integer', 'exists:subjects,id'],
        ];
    }
}
