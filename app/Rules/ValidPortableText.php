<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a document against the site's Portable Text dialect: `block`
 * nodes (styles normal/h2/h3/blockquote, spans carrying strong/em/code or
 * link-markDef marks, optional bullet/number list items) plus the custom
 * `image`, `code` and `divider` nodes. Mirrors docs/portable-text.schema.json,
 * which is the shareable contract for authoring clients.
 */
class ValidPortableText implements ValidationRule
{
    private const STYLES = ['normal', 'h2', 'h3', 'blockquote'];

    private const DECORATORS = ['strong', 'em', 'code'];

    private const LIST_ITEMS = ['bullet', 'number'];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $fail("The {$attribute} must be a list of Portable Text nodes.");

            return;
        }

        foreach ($value as $index => $node) {
            $error = $this->nodeError($node);

            if ($error !== null) {
                $fail("The {$attribute} node #{$index} is invalid: {$error}");

                return;
            }
        }
    }

    private function nodeError(mixed $node): ?string
    {
        if (! is_array($node)) {
            return 'not an object';
        }

        if (! $this->nonEmptyString($node['_key'] ?? null)) {
            return 'missing _key';
        }

        return match ($node['_type'] ?? null) {
            'block' => $this->blockError($node),
            'image' => $this->nonEmptyString($node['url'] ?? null) && filter_var($node['url'], FILTER_VALIDATE_URL) !== false
                ? null
                : 'image requires a valid url',
            'code' => $this->codeError($node),
            'divider' => null,
            default => 'unknown node _type',
        };
    }

    private function codeError(array $node): ?string
    {
        if (! $this->nonEmptyString($node['code'] ?? null)) {
            return 'code node requires code';
        }

        foreach (['language', 'filename'] as $optional) {
            if (array_key_exists($optional, $node) && ! $this->nonEmptyString($node[$optional])) {
                return "code {$optional} must be a non-empty string when present";
            }
        }

        if (array_key_exists('lineNumbers', $node) && ! is_bool($node['lineNumbers'])) {
            return 'code lineNumbers must be a boolean when present';
        }

        return null;
    }

    private function blockError(array $node): ?string
    {
        if (! in_array($node['style'] ?? null, self::STYLES, true)) {
            return 'unknown block style';
        }

        if (array_key_exists('listItem', $node) && ! in_array($node['listItem'], self::LIST_ITEMS, true)) {
            return 'unknown listItem';
        }

        $markDefs = $node['markDefs'] ?? [];

        if (! is_array($markDefs) || ! array_is_list($markDefs)) {
            return 'markDefs must be a list';
        }

        $linkKeys = [];

        foreach ($markDefs as $def) {
            if (! is_array($def)
                || ($def['_type'] ?? null) !== 'link'
                || ! $this->nonEmptyString($def['_key'] ?? null)
                || filter_var($def['href'] ?? '', FILTER_VALIDATE_URL) === false) {
                return 'markDefs must be link definitions with a _key and valid href';
            }

            $linkKeys[] = $def['_key'];
        }

        $children = $node['children'] ?? [];

        if (! is_array($children) || ! array_is_list($children) || $children === []) {
            return 'block requires at least one span child';
        }

        foreach ($children as $child) {
            $error = $this->spanError($child, $linkKeys);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $linkKeys
     */
    private function spanError(mixed $span, array $linkKeys): ?string
    {
        if (! is_array($span) || ($span['_type'] ?? null) !== 'span') {
            return 'block children must be spans';
        }

        if (! $this->nonEmptyString($span['_key'] ?? null)) {
            return 'span missing _key';
        }

        if (! is_string($span['text'] ?? null)) {
            return 'span missing text';
        }

        $marks = $span['marks'] ?? [];

        if (! is_array($marks) || ! array_is_list($marks)) {
            return 'span marks must be a list';
        }

        foreach ($marks as $mark) {
            if (! is_string($mark)
                || (! in_array($mark, self::DECORATORS, true) && ! in_array($mark, $linkKeys, true))) {
                return 'span mark must be a decorator or reference a markDef _key';
            }
        }

        return null;
    }

    private function nonEmptyString(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
