<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One option a dynamic tag accepts. An empty `choices` means any string is
 * allowed; a populated one is the closed set the editor draws as a select.
 */
final readonly class TagOption implements Arrayable, JsonSerializable
{
    /**
     * An empty `$choices` accepts any string; a populated one is a closed set.
     * `boolean` draws a checkbox instead of a select/text field, for an
     * on/off option like a tag's `icon` toggle.
     *
     * @param  list<string>  $choices
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $choices = [],
        public ?string $default = null,
        public bool $boolean = false,
    ) {}

    /**
     * The shape the tag-editor UI reads to render this option's field.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'choices' => $this->choices,
            'default' => $this->default,
            'boolean' => $this->boolean,
        ];
    }

    /**
     * Delegates to {@see toArray()}, so JSON output matches it exactly.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
