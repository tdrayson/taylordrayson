<?php

namespace App\Data;

use App\Enums\FieldType;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One editable field on an entry. `primary` fields are always visible; the rest
 * hide behind "+ Add field" until they have a value.
 */
final readonly class FieldData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<array{value: string, label: string}>  $options  Choices, for a Select field.
     * @param  string|null  $source  Which /lookup source backs a Lookup or Location field.
     * @param  string|null  $prefix  Unit shown inside the input, before the value ("£").
     * @param  string|null  $suffix  Unit shown inside the input, after the value ("L").
     * @param  string|null  $group  Fields sharing a group are offered as one item, e.g. "Address".
     * @param  string|null  $pairsWith  Drawn inside that field's control instead of as its own row.
     * @param  string|null  $collection  Media Library collection an Image or Gallery field syncs to.
     * @param  bool  $hidden  Saved and filled by a lookup, but never offered in the UI.
     */
    private function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public bool $primary,
        public bool $required,
        public ?string $help,
        public array $options,
        public ?string $source,
        public bool $defaultsToNow,
        public ?string $relativeTo,
        public ?string $prefix,
        public ?string $suffix,
        public ?string $group,
        public ?string $pairsWith,
        public ?string $collection,
        public bool $hidden,
    ) {}

    /**
     * A field that is always shown. `required` is separate: primary controls
     * visibility, required controls whether a save is refused without it.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function primary(string $name, string $label, FieldType $type, ?string $help = null, array $options = [], bool $required = false, ?string $source = null, bool $defaultsToNow = false, ?string $relativeTo = null, ?string $prefix = null, ?string $suffix = null, ?string $group = null, ?string $pairsWith = null, ?string $collection = null): self
    {
        return new self($name, $label, $type, true, $required, $help, $options, $source, $defaultsToNow, $relativeTo, $prefix, $suffix, $group, $pairsWith, $collection, false);
    }

    /**
     * A field kept behind "+ Add field" until it is wanted.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function optional(string $name, string $label, FieldType $type, ?string $help = null, array $options = [], ?string $source = null, bool $defaultsToNow = false, ?string $relativeTo = null, ?string $prefix = null, ?string $suffix = null, ?string $group = null, ?string $pairsWith = null, ?string $collection = null): self
    {
        return new self($name, $label, $type, false, false, $help, $options, $source, $defaultsToNow, $relativeTo, $prefix, $suffix, $group, $pairsWith, $collection, false);
    }

    /**
     * A field the UI never offers, kept so a lookup can fill it and a save can
     * carry it: coordinates come from picking a place, not from typing.
     */
    public static function hidden(string $name, string $label, FieldType $type): self
    {
        return new self($name, $label, $type, false, false, null, [], null, false, null, null, null, null, null, null, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'primary' => $this->primary,
            'required' => $this->required,
            'isBody' => $this->type->isBody(),
            'isTitle' => $this->type->isTitle(),
            'isPublished' => $this->type->isPublished(),
        ];

        if ($this->help !== null) {
            $data['help'] = $this->help;
        }

        if ($this->options !== []) {
            $data['options'] = $this->options;
        }

        if ($this->source !== null) {
            $data['source'] = $this->source;
        }

        if ($this->defaultsToNow) {
            $data['defaultsToNow'] = true;
        }

        if ($this->relativeTo !== null) {
            $data['relativeTo'] = $this->relativeTo;
        }

        if ($this->prefix !== null) {
            $data['prefix'] = $this->prefix;
        }

        if ($this->suffix !== null) {
            $data['suffix'] = $this->suffix;
        }

        if ($this->group !== null) {
            $data['group'] = $this->group;
        }

        if ($this->pairsWith !== null) {
            $data['pairsWith'] = $this->pairsWith;
        }

        if ($this->hidden) {
            $data['hidden'] = true;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
