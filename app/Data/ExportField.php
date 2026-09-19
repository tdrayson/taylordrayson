<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One published value on an export: what to call it, how it reads, and the
 * machine value behind it in base units.
 */
final readonly class ExportField implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $key,
        public string $label,
        public string $display,
        public mixed $raw,
    ) {}

    /** `raw` defaults to the display string, for a value with nothing more machine-friendly to say. */
    public static function make(string $key, string $label, string $display, mixed $raw = null): self
    {
        return new self($key, $label, $display, $raw ?? $display);
    }

    /** The same field, dropped entirely when the entry has no value for it. */
    public static function maybe(string $key, string $label, ?string $display, mixed $raw = null): ?self
    {
        return $display === null || $display === '' ? null : self::make($key, $label, $display, $raw);
    }

    /**
     * @return array{key: string, label: string, display: string, raw: mixed}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'display' => $this->display, 'raw' => $this->raw];
    }

    /**
     * @return array{key: string, label: string, display: string, raw: mixed}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
