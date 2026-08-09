<?php

namespace App\Data;

use App\Enums\FieldType;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One editable field on an entry.
 *
 * `primary` decides where it appears: primary fields are always visible in the
 * form or the properties panel, everything else hides behind "+ Add field" and
 * only surfaces once it has a value. That split is declared here, once, and
 * drives the form, the panel and the menu alike.
 */
final readonly class FieldData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<array{value: string, label: string}>  $options  Choices, for a Select field.
     * @param  string|null  $source  Which /lookup source backs a Lookup or Location field.
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
    ) {}

    /**
     * A field that is always shown.
     *
     * `required` is separate from `primary` on purpose: primary decides whether
     * a field is visible, required decides whether a save is refused without
     * it. Tags are always shown and almost never mandatory.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function primary(string $name, string $label, FieldType $type, ?string $help = null, array $options = [], bool $required = false, ?string $source = null, bool $defaultsToNow = false, ?string $relativeTo = null): self
    {
        return new self($name, $label, $type, true, $required, $help, $options, $source, $defaultsToNow, $relativeTo);
    }

    /**
     * A field kept behind "+ Add field" until it is wanted.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function optional(string $name, string $label, FieldType $type, ?string $help = null, array $options = [], ?string $source = null, bool $defaultsToNow = false, ?string $relativeTo = null): self
    {
        return new self($name, $label, $type, false, false, $help, $options, $source, $defaultsToNow, $relativeTo);
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
