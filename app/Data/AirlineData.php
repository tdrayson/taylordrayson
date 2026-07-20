<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The operating airline shown on a flight card: name, icon mark, and the
 * flight designator (IATA/ICAO code plus number).
 */
final readonly class AirlineData implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?string $name,
        public ?string $icon,
        public string $number,
    ) {}

    /**
     * @return array{name: ?string, icon: ?string, number: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'icon' => $this->icon,
            'number' => $this->number,
        ];
    }

    /**
     * @return array{name: ?string, icon: ?string, number: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
