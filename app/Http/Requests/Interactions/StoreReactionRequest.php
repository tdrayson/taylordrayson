<?php

namespace App\Http\Requests\Interactions;

use App\Enums\ReactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReactionRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ReactionType::class)],
        ];
    }

    public function reactionType(): ReactionType
    {
        return ReactionType::from($this->validated('type'));
    }
}
