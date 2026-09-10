<?php

namespace App\Http\Requests;

use App\DynamicTags\DynamicTagRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A tag name plus an arbitrary option set to resolve. `name` is checked against
 * the registry so an unregistered tag is never resolved, and each option key is
 * checked against that tag's own declared schema so the popup cannot ask the
 * server to evaluate an option a tag doesn't accept.
 */
class ResolveDynamicTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(DynamicTagRegistry $registry): array
    {
        return [
            'name' => ['required', 'string', Rule::in(array_keys($registry->all()))],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(DynamicTagRegistry $registry): array
    {
        return [
            function (Validator $validator) use ($registry): void {
                $tag = $registry->find((string) $this->input('name'));

                if ($tag === null) {
                    return;
                }

                $allowed = array_column($tag->options(), 'name');

                foreach (array_diff(array_keys((array) $this->input('options', [])), $allowed) as $key) {
                    $validator->errors()->add("options.{$key}", "Unknown option for {$tag->name()}.");
                }
            },
        ];
    }
}
