<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class CsvLookupRows
{
    /**
     * @return list<array<string, string|null>>
     */
    public static function from(string $absolutePath): array
    {
        if (! File::isFile($absolutePath)) {
            return [];
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            return [];
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');

            if ($headers === false) {
                return [];
            }

            $headers = array_map(static fn (?string $header): string => (string) $header, $headers);
            $rows = [];

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                if ($values === [null] || $values === false) {
                    continue;
                }

                $combined = [];

                foreach ($headers as $index => $header) {
                    $value = $values[$index] ?? null;
                    $combined[$header] = $value === '' ? null : $value;
                }

                $rows[] = $combined;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
