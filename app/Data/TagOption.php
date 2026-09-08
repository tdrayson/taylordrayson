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
     * @param  list<string>  $choices
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $choices = [],
        public ?string $default = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'choices' => $this->choices,
            'default' => $this->default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
