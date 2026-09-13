<?php

namespace App\Http\Requests;

use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;
use App\Rules\ValidPortableText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A tag name plus an arbitrary option set to resolve. `name` is checked against
 * the registry so an unregistered tag is never resolved, and each option is
 * checked against that tag's own declared schema, both the key and the value,
 * so the popup cannot ask the server to evaluate an option a tag doesn't
 * accept or a value it doesn't declare. Mirrors {@see ValidPortableText::tagError()},
 * the save-time check for the same shape stored on a document. `placement`
 * decides whether the preview is the display text or the resolved href.
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
            'placement' => ['required', Rule::enum(Placement::class)],
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

                $declared = collect($tag->options())->keyBy('name');

                foreach ((array) $this->input('options', []) as $key => $value) {
                    $option = $declared->get($key);

                    if ($option === null) {
                        $validator->errors()->add("options.{$key}", "Unknown option for {$tag->name()}.");

                        continue;
                    }

                    // A bare year is accepted alongside period's own named presets.
                    if ($key === 'period' && preg_match('/^\d{4}$/', (string) $value) === 1) {
                        continue;
                    }

                    if ($option->choices !== [] && ! in_array($value, $option->choices, true)) {
                        $validator->errors()->add("options.{$key}", "Invalid value for option {$key}.");
                    }
                }
            },
        ];
    }
}
