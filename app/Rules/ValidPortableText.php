<?php

namespace App\Rules;

use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a document against the site's Portable Text dialect: `block`
 * nodes (styles normal/h2-h6/blockquote, spans carrying strong/em/underline/
 * strike-through/code or link/dynamicHref-markDef marks, optional bullet/number
 * list items) plus the custom `image`, `code`, `callout`, `video` and `divider`
 * nodes, and a `dynamicTag` child, `dynamicHref` markDef or tagged `image` for
 * each {@see Placement} a registered dynamic tag supports. Mirrors
 * docs/reference/portable-text.schema.json, which is the shareable contract for
 * authoring clients.
 */
class ValidPortableText implements ValidationRule
{
    private const STYLES = ['normal', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'];

    private const DECORATORS = ['strong', 'em', 'code', 'underline', 'strike-through'];

    private const LIST_ITEMS = ['bullet', 'number'];

    private const CALLOUT_VARIANTS = ['note', 'tip', 'important', 'warning', 'caution'];

    /**
     * Run the validation rule.
     *
     * A `Prose` field also accepts a plain string (see {@see TextOrDocument}),
     * which is not a Portable Text document to walk, so it passes untouched here.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            return;
        }

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
            'image' => $this->imageError($node),
            'code' => $this->codeError($node),
            'callout' => $this->calloutError($node),
            'video' => $this->videoError($node),
            'divider' => null,
            default => 'unknown node _type',
        };
    }

    private function imageError(array $node): ?string
    {
        if (isset($node['tag'])) {
            return $this->tagError($node, Placement::Image);
        }

        $url = $node['url'] ?? null;

        // Absolute URLs or root-relative paths (own-hosted media is stored
        // domain-portable, e.g. /storage/...).
        $validUrl = $this->nonEmptyString($url)
            && (filter_var($url, FILTER_VALIDATE_URL) !== false || preg_match('#^/[^/]#', $url) === 1);

        if (! $validUrl) {
            return 'image requires a valid url';
        }

        foreach (['width', 'height'] as $dimension) {
            $dimensionValue = $node[$dimension] ?? null;

            if ($dimensionValue !== null && (! is_int($dimensionValue) || $dimensionValue < 1)) {
                return "image {$dimension} must be a positive integer when present";
            }
        }

        return null;
    }

    private function videoError(array $node): ?string
    {
        $url = $node['url'] ?? null;

        // Same rule as image: absolute URLs or root-relative paths (own-hosted
        // media is stored domain-portable, e.g. /storage/...).
        $validUrl = $this->nonEmptyString($url)
            && (filter_var($url, FILTER_VALIDATE_URL) !== false || preg_match('#^/[^/]#', $url) === 1);

        if (! $validUrl) {
            return 'video requires a valid url';
        }

        if (array_key_exists('poster', $node) && $node['poster'] !== null) {
            $poster = $node['poster'];
            $validPoster = $this->nonEmptyString($poster)
                && (filter_var($poster, FILTER_VALIDATE_URL) !== false || preg_match('#^/[^/]#', $poster) === 1);

            if (! $validPoster) {
                return 'video poster must be a valid url when present';
            }
        }

        foreach (['width', 'height'] as $dimension) {
            $dimensionValue = $node[$dimension] ?? null;

            if ($dimensionValue !== null && (! is_int($dimensionValue) || $dimensionValue < 1)) {
                return "video {$dimension} must be a positive integer when present";
            }
        }

        return null;
    }

    private function calloutError(array $node): ?string
    {
        if (! in_array($node['variant'] ?? null, self::CALLOUT_VARIANTS, true)) {
            return 'callout requires a valid variant';
        }

        return $this->richTextError($node, 'callout');
    }

    private function codeError(array $node): ?string
    {
        if (! $this->nonEmptyString($node['code'] ?? null)) {
            return 'code node requires code';
        }

        foreach (['language', 'filename'] as $optional) {
            $optionalValue = $node[$optional] ?? null;

            if ($optionalValue !== null && ! $this->nonEmptyString($optionalValue)) {
                return "code {$optional} must be a non-empty string when present";
            }
        }

        $lineNumbers = $node['lineNumbers'] ?? null;

        if ($lineNumbers !== null && ! is_bool($lineNumbers)) {
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

        return $this->richTextError($node, 'block');
    }

    /**
     * Shared markDefs + children validation for any node that carries one
     * rich-text paragraph (currently `block` and `callout`).
     */
    private function richTextError(array $node, string $label): ?string
    {
        $markDefs = $node['markDefs'] ?? [];

        if (! is_array($markDefs) || ! array_is_list($markDefs)) {
            return 'markDefs must be a list';
        }

        $linkKeys = [];

        foreach ($markDefs as $def) {
            if (! is_array($def) || ! $this->nonEmptyString($def['_key'] ?? null)) {
                return 'markDefs must be link or dynamicHref definitions with a _key';
            }

            if (($def['_type'] ?? null) === 'dynamicHref') {
                $error = $this->tagError($def, Placement::Href);

                if ($error !== null) {
                    return $error;
                }
            } elseif (($def['_type'] ?? null) !== 'link' || filter_var($def['href'] ?? '', FILTER_VALIDATE_URL) === false) {
                return 'markDefs must be link definitions with a _key and valid href';
            }

            $linkKeys[] = $def['_key'];
        }

        $children = $node['children'] ?? [];

        if (! is_array($children) || ! array_is_list($children)) {
            return "{$label} requires at least one span child";
        }

        // A list item the editor has just created and the author has not
        // typed into yet has no span children; other blocks still need one.
        if ($children === [] && ! isset($node['listItem'])) {
            return "{$label} requires at least one span child";
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
        if (is_array($span) && ($span['_type'] ?? null) === 'dynamicTag') {
            if (! $this->nonEmptyString($span['_key'] ?? null)) {
                return 'dynamic tag missing _key';
            }

            return $this->tagError($span, Placement::Inline);
        }

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

    /**
     * Resolved through the container rather than injected, so the rule keeps
     * working with `new ValidPortableText` while still sharing the
     * request-scoped registry everywhere else uses it.
     */
    private function registry(): DynamicTagRegistry
    {
        return app(DynamicTagRegistry::class);
    }

    /**
     * A tag must be registered, legal in this placement, and carry only options
     * the tag declares with values it accepts.
     *
     * @param  array<string, mixed>  $node
     */
    private function tagError(array $node, Placement $placement): ?string
    {
        $name = $node['tag'] ?? null;

        if (! $this->nonEmptyString($name)) {
            return 'unknown dynamic tag';
        }

        $tag = $this->registry()->find($name);

        if ($tag === null) {
            return 'unknown dynamic tag';
        }

        if (! in_array($placement, $tag->supports(), true)) {
            return "dynamic tag {$tag->name()} is not allowed as {$placement->value}";
        }

        $options = $node['options'] ?? [];

        if (! is_array($options)) {
            return 'dynamic tag options must be an object';
        }

        $declared = collect($tag->options())->keyBy('name');

        foreach ($options as $optionName => $value) {
            $option = $declared->get($optionName);

            if ($option === null) {
                return "unknown option {$optionName}";
            }

            // A bare year is accepted alongside the named presets.
            if ($optionName === 'period' && preg_match('/^\d{4}$/', (string) $value) === 1) {
                continue;
            }

            if ($option->choices !== [] && ! in_array($value, $option->choices, true)) {
                return "invalid value for option {$optionName}";
            }
        }

        return null;
    }
}
