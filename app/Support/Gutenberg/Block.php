<?php

namespace App\Support\Gutenberg;

/**
 * One WordPress block: its name, the JSON attributes on its delimiter, the
 * markup between the delimiters, and any blocks nested inside it.
 */
final readonly class Block
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<Block>  $children
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
        public string $innerHtml = '',
        public array $children = [],
    ) {}

    /**
     * A nested attribute by dot path, for the Blockstudio blocks that bury
     * everything under `blockstudio.attributes`.
     */
    public function attribute(string $path, mixed $default = null): mixed
    {
        $value = $this->attributes;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
