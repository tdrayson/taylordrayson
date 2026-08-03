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
     */
    private function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public bool $primary,
        public ?string $help,
        public array $options,
    ) {}

    /**
     * A field that is always shown.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function primary(string $name, string $label, FieldType $type, ?string $help = null, array $options = []): self
    {
        return new self($name, $label, $type, true, $help, $options);
    }

    /**
     * A field kept behind "+ Add field" until it is wanted.
     *
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function optional(string $name, string $label, FieldType $type, ?string $help = null, array $options = []): self
    {
        return new self($name, $label, $type, false, $help, $options);
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
            'isBody' => $this->type->isBody(),
        ];

        if ($this->help !== null) {
            $data['help'] = $this->help;
        }

        if ($this->options !== []) {
            $data['options'] = $this->options;
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
