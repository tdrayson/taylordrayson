<?php

namespace App\Http\Requests\Interactions;

use App\Enums\ReactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReactionRequest extends FormRequest
{
    /**
     * Reactions are for visitors. Signed in means the author, and reacting to
     * your own entry is not a gesture worth recording.
     */
    public function authorize(): bool
    {
        return $this->user() === null;
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
