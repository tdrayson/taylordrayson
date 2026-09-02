<?php

namespace App\Http\Requests;

use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
     * UpsertSubject falls back to Str::slug($name) when no slug is submitted;
     * mirrored here so that fallback is validated for uniqueness too, rather
     * than only a slug the caller happened to send explicitly.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::enum(SubjectKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('subjects')->where(fn ($query) => $query->where('kind', $this->kind()))->ignore($this->route('subject')),
            ],
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
     * A category belongs to one kind, which the enum cannot enforce alone: the
     * kind being validated is the submitted one, or on update, the route-bound subject's.
     */
    private function belongsToKind(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if ($this->kind() === null || SubjectCategory::from($value)->kind() !== $this->kind()) {
                $fail('That category belongs to a different kind.');
            }
        };
    }

    /**
     * The kind being validated: the submitted one on create, or the
     * route-bound subject's own on update (kind is never resubmitted there).
     */
    private function kind(): ?SubjectKind
    {
        return $this->has('kind')
            ? SubjectKind::tryFrom((string) $this->input('kind'))
            : $this->route('subject')?->kind;
    }
}
