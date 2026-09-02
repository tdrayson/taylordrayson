<?php

namespace App\Http\Requests;

use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a subject. Authentication is enforced by the route's `auth`
 * middleware, not here.
 */
class SubjectRequest extends FormRequest
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
            'kind' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::enum(SubjectKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'category' => ['nullable', Rule::enum(SubjectCategory::class), $this->belongsToKind()],
            'bio' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
            'meta.*.label' => ['nullable', 'string', 'max:255'],
            'meta.*.value' => ['nullable', 'string', 'max:255'],
            'identities' => ['nullable', 'array'],
            'identities.*.platform' => ['nullable', 'string', 'max:255'],
            'identities.*.value' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'cover' => ['nullable', 'array', 'max:1'],
            'cover.*' => ['string', 'max:100'],
        ];
    }

    /**
     * A category belongs to one kind, which the enum cannot enforce alone. The
     * kind being validated is either freshly submitted (create) or, on an
     * update where it is settled and no longer resubmitted, the route-bound
     * subject's own.
     */
    private function belongsToKind(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            $kind = $this->has('kind')
                ? SubjectKind::tryFrom((string) $this->input('kind'))
                : $this->route('subject')?->kind;

            if ($kind === null || SubjectCategory::from($value)->kind() !== $kind) {
                $fail('That category belongs to a different kind.');
            }
        };
    }
}
