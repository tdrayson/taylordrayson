<?php

namespace App\Data;

use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** When an entry happened, as a sentence and as a machine instant. */
final readonly class ExportInstant implements Arrayable, JsonSerializable
{
    private function __construct(
        public string $display,
        public string $iso,
        public string $timezone,
    ) {}

    public static function for(CarbonInterface $occurredAt, ?string $timezone): self
    {
        $zone = $timezone ?: (string) config('app.home_timezone');
        $local = LocalTime::for($occurredAt, $zone);

        return new self(
            display: $occurredAt->format('j F Y').' at '.$occurredAt->format('H:i'),
            iso: $local['iso'],
            timezone: $zone,
        );
    }

    /**
     * @return array{display: string, iso: string, timezone: string}
     */
    public function toArray(): array
    {
        return ['display' => $this->display, 'iso' => $this->iso, 'timezone' => $this->timezone];
    }

    /**
     * @return array{display: string, iso: string, timezone: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
