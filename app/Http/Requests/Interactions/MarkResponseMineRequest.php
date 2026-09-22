<?php

namespace App\Http\Requests\Interactions;

use App\Enums\WebmentionKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkResponseMineRequest extends FormRequest
{
    /**
     * Replies only. Likes and kudos are rebuilt on every sync, so a mark on one
     * would be gone within the hour, and nobody can kudo their own post anyway.
     */
    public function authorize(): bool
    {
        return $this->route('response')?->kind === WebmentionKind::Reply;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mine' => ['required', 'boolean'],
        ];
    }
}
