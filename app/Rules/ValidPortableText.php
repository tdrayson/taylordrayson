<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a document against the site's Portable Text dialect: `block`
 * nodes (styles normal/h2-h6/blockquote, spans carrying strong/em/code or
 * link-markDef marks, optional bullet/number list items) plus the custom
 * `image`, `code`, `callout`, `video` and `divider` nodes. Mirrors
 * docs/reference/portable-text.schema.json, which is the shareable contract for
 * authoring clients.
 */
class ValidPortableText implements ValidationRule
{
    private const STYLES = ['normal', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'];

    private const DECORATORS = ['strong', 'em', 'code'];

    private const LIST_ITEMS = ['bullet', 'number'];

    private const CALLOUT_VARIANTS = ['note', 'tip', 'important', 'warning', 'caution'];

    private const FILE_SOURCES = ['upload', 'github'];

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
            'image' => $this->imageError($node),
            'code' => $this->codeError($node),
            'callout' => $this->calloutError($node),
            'video' => $this->videoError($node),
            'file' => $this->fileError($node),
            'divider' => null,
            default => 'unknown node _type',
        };
    }

    private function imageError(array $node): ?string
    {
        $url = $node['url'] ?? null;

        // Absolute URLs or root-relative paths (own-hosted media is stored
        // domain-portable, e.g. /storage/...).
        $validUrl = $this->nonEmptyString($url)
            && (filter_var($url, FILTER_VALIDATE_URL) !== false || preg_match('#^/[^/]#', $url) === 1);

        if (! $validUrl) {
            return 'image requires a valid url';
        }

        foreach (['width', 'height'] as $dimension) {
            if (array_key_exists($dimension, $node) && (! is_int($node[$dimension]) || $node[$dimension] < 1)) {
                return "image {$dimension} must be a positive integer when present";
            }
        }

        return null;
    }

    /**
     * A file is one of two shapes. An upload carries the URL it was attached
     * at; a release carries the repository and asset it is looked up by, and
     * has no URL of its own because the newest one is not known here.
     */
    private function fileError(array $node): ?string
    {
        $source = $node['source'] ?? 'upload';

        if (! in_array($source, self::FILE_SOURCES, true)) {
            return 'file source must be one of: '.implode(', ', self::FILE_SOURCES);
        }

        if ($source === 'github') {
            // owner/name, the only form the download URL can be built from.
            if (! $this->nonEmptyString($node['repo'] ?? null) || preg_match('#^[^/\s]+/[^/\s]+$#', $node['repo']) !== 1) {
                return 'file repo must be owner/name';
            }

            return $this->nonEmptyString($node['asset'] ?? null) ? null : 'file requires an asset';
        }

        $url = $node['url'] ?? null;

        $validUrl = $this->nonEmptyString($url)
            && (filter_var($url, FILTER_VALIDATE_URL) !== false || preg_match('#^/[^/]#', $url) === 1);

        if (! $validUrl) {
            return 'file requires a valid url';
        }

        if (array_key_exists('size', $node) && $node['size'] !== null && (! is_int($node['size']) || $node['size'] < 0)) {
            return 'file size must be a non-negative integer when present';
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
            if (array_key_exists($dimension, $node) && (! is_int($node[$dimension]) || $node[$dimension] < 1)) {
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
