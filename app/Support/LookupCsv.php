<?php

namespace App\Support;

use RuntimeException;

class LookupCsv
{
    /**
     * Read a lookup CSV into rows keyed by column header.
     *
     * Throws rather than returning an empty set when the file is unreadable:
     * an empty set would be cached by Sushi and surface as missing data
     * instead of an error.
     *
     * @return list<array<string, string|null>>
     */
    public static function from(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Lookup CSV not found: {$absolutePath}");
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Lookup CSV could not be opened: {$absolutePath}");
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');

            if ($headers === false) {
                throw new RuntimeException("Lookup CSV has no header row: {$absolutePath}");
            }

            $headers = array_map(static fn (?string $header): string => (string) $header, $headers);
            $rows = [];

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                $row = [];

                foreach ($headers as $index => $header) {
                    $value = $values[$index] ?? null;
                    $row[$header] = ($value === '' || $value === null) ? null : $value;
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
