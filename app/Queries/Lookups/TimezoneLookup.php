<?php

namespace App\Queries\Lookups;

use DateTimeZone;

/**
 * IANA timezones, searchable by name or by city.
 *
 * Straight from PHP's own list rather than a curated set: the whole point of
 * storing a zone per entry is that travel puts you somewhere unanticipated.
 */
final class TimezoneLookup
{
    /**
     * @return list<array{value: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        $identifiers = DateTimeZone::listIdentifiers();

        if ($query !== '') {
            $needle = str_replace(' ', '_', strtolower($query));
            $identifiers = array_values(array_filter(
                $identifiers,
                fn (string $identifier): bool => str_contains(strtolower($identifier), $needle),
            ));
        }

        return array_map(function (string $identifier): array {
            // The city is what anyone actually searches for, so it leads and
            // the region sits beside it as context.
            $parts = explode('/', $identifier);
            $city = str_replace('_', ' ', end($parts));

            return [
                'value' => $identifier,
                'label' => $city,
                'detail' => $this->offset($identifier),
            ];
        }, array_slice($identifiers, 0, 12));
    }

    private function offset(string $identifier): string
    {
        $minutes = (new DateTimeZone($identifier))->getOffset(new \DateTimeImmutable) / 60;
        $sign = $minutes < 0 ? '-' : '+';
        $minutes = abs($minutes);

        return sprintf('%s%02d:%02d', $sign, intdiv($minutes, 60), $minutes % 60);
    }
}
