<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The editor's unsaved form values. Deliberately lenient: an incomplete form
 * still previews, and unknown keys are dropped by the action.
 */
class SharePreviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
        ];
    }
}
