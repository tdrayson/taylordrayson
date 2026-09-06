<?php

namespace App\Http\Requests\Interactions;

use App\Data\CommentSubmission;
use App\Rules\ContributedDocument;
use App\Support\PortableText;
use App\Support\ProfanityFilter;
use App\Support\VisitorIdentity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * The field a real browser hides and never fills. Named for something a
     * form-filling bot expects to see rather than anything obviously a trap.
     */
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge([
            'author_name' => trim(strip_tags((string) $this->input('author_name'))),
            // Only a string is stripped. Casting a document to a string to run
            // strip_tags over it would flatten the whole comment to "Array".
            'body' => is_string($body) ? trim(strip_tags($body)) : $body,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'author_name' => [
                'required',
                'string',
                'min:2',
                'max:60',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (ProfanityFilter::blocksName((string) $value)) {
                        $fail('Please use a different name.');
                    }
                },
            ],

            // Optional and never a spam control: an unverified address stops
            // no bots, and is the field they fill most reliably.
            'author_email' => ['nullable', 'email:rfc', 'max:255'],
            'notify_replies' => ['boolean'],

            'body' => $this->bodyRules(),
            'parent_id' => ['nullable', 'integer'],
            'nonce' => ['required', 'string', 'uuid'],
            self::HONEYPOT => ['nullable', 'string'],
        ];
    }

    /**
     * A document from the editor, or a plain string from a client without one.
     * Both are stored as Portable Text; only the document has a shape worth
     * checking, and it is checked against a narrower allowlist than anything
     * the site itself publishes.
     *
     * @return array<int, ValidationRule|string>
     */
    private function bodyRules(): array
    {
        return is_array($this->input('body'))
            ? ['required', 'array', new ContributedDocument]
            : ['required', 'string', 'min:2', 'max:4000'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'author_name.required' => 'Add a name so people know who replied.',
            'body.required' => 'Write something first.',
            'body.max' => 'That is longer than a comment box can take.',
            'nonce.required' => 'This form went stale. Reload the page and try again.',
        ];
    }

    /**
     * The comment as Portable Text, whatever shape it arrived in.
     *
     * @return array<int, array<string, mixed>>
     */
    private function document(): array
    {
        $body = $this->validated('body');

        return is_array($body) ? $body : PortableText::fromPlainText($body);
    }

    public function submission(): CommentSubmission
    {
        return new CommentSubmission(
            authorName: $this->validated('author_name'),
            authorEmail: $this->validated('author_email'),
            notifyReplies: $this->boolean('notify_replies'),
            body: $this->document(),
            parentId: $this->validated('parent_id'),
            nonce: $this->validated('nonce'),
            honeypotFilled: filled($this->input(self::HONEYPOT)),
            ipHash: VisitorIdentity::reputation($this),
            userAgent: $this->userAgent(),
        );
    }
}
