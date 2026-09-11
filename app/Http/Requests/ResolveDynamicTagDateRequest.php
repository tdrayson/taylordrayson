<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The free-text `value` a dynamic tag's date field asks the server to resolve. */
class ResolveDynamicTagDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string'],
        ];
    }
}
