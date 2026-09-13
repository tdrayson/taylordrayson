<?php

namespace App\Http\Requests\Citations;

use App\Enums\ResponseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:500'],
            'kind' => ['required', Rule::enum(ResponseKind::class)],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.url' => 'That does not look like a link to a post.',
            'kind.required' => 'Choose what kind of response this is first.',
        ];
    }
}
