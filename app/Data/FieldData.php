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
     * @param  string|null  $collection  Media Library collection an Image or Gallery field syncs to.
     * @param  bool  $hidden  Saved and filled by a lookup, but never offered in the UI.
     * @param  string|null  $fallback  What a Slug field resolves to when left empty.
     * @param  int|null  $max  Character limit on the field's readable text, drawn as a counter.
     * @param  bool  $checksReservedSlug  Whether a Slug field is rejected when it matches a dataset's reserved day-URL word.
     * @param  bool  $readOnly  Shown for reference but never posted, since something other than the form writes it.
     */
    private function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public bool $primary,
        public bool $required,
        public array $options,
        public ?string $source,
        public bool $defaultsToNow,
        public ?string $relativeTo,
        public ?string $prefix,
        public ?string $suffix,
        public ?string $group,
        public ?string $collection,
        public bool $hidden,
        public ?string $fallback,
        public ?int $max,
        public bool $checksReservedSlug,
        public bool $readOnly,
    ) {}

    /**
     * A field that is always shown. `required` is separate: primary controls
     * visibility, required controls whether a save is refused without it.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function primary(string $name, string $label, FieldType $type, array $options = [], bool $required = false, ?string $source = null, bool $defaultsToNow = false, ?string $relativeTo = null, ?string $prefix = null, ?string $suffix = null, ?string $group = null, ?string $collection = null, ?string $fallback = null, ?int $max = null, bool $checksReservedSlug = false): self
    {
        return new self($name, $label, $type, true, $required, $options, $source, $defaultsToNow, $relativeTo, $prefix, $suffix, $group, $collection, false, $fallback, $max, $checksReservedSlug, false);
    }

    /**
     * A field kept behind "+ Add field" until it is wanted.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function optional(string $name, string $label, FieldType $type, array $options = [], ?string $source = null, bool $defaultsToNow = false, ?string $relativeTo = null, ?string $prefix = null, ?string $suffix = null, ?string $group = null, ?string $collection = null, ?string $fallback = null, ?int $max = null, bool $checksReservedSlug = false): self
    {
        return new self($name, $label, $type, false, false, $options, $source, $defaultsToNow, $relativeTo, $prefix, $suffix, $group, $collection, false, $fallback, $max, $checksReservedSlug, false);
    }

    /**
     * A field the UI never offers, kept so a lookup can fill it and a save can
     * carry it: coordinates come from picking a place, not from typing.
     */
    public static function hidden(string $name, string $label, FieldType $type): self
    {
        return new self($name, $label, $type, false, false, [], null, false, null, null, null, null, null, true, null, null, false, false);
    }

    /**
     * A field drawn read-only, for a value a sync owns.
     */
    public static function readOnly(string $name, string $label, FieldType $type, ?string $suffix = null): self
    {
        return new self($name, $label, $type, true, false, [], null, false, null, null, $suffix, null, null, false, null, null, false, true);
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
        ];

        if ($this->options !== []) {
            $data['options'] = $this->options;
        }

        if ($this->max !== null) {
            $data['max'] = $this->max;
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

        if ($this->fallback !== null) {
            $data['fallback'] = $this->fallback;
        }

        if ($this->hidden) {
            $data['hidden'] = true;
        }

        if ($this->readOnly) {
            $data['readOnly'] = true;
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
