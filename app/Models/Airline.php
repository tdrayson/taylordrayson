<?php

namespace App\Models;

use App\Models\Concerns\HasLookupCsv;
use Database\Factories\AirlineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'country',
])]
class Airline extends Model
{
    /** @use HasFactory<AirlineFactory> */
    use HasFactory;

    use HasLookupCsv;

    public $timestamps = false;

    /**
     * Declared explicitly because getRows() is empty under test, leaving
     * Sushi nothing to infer the column types from.
     *
     * @var array<string, string>
     */
    protected $schema = [
        'iata_code' => 'string',
        'icao_code' => 'string',
        'name' => 'string',
        'country' => 'string',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['icon_url', 'logo_url'];

    protected function lookupPath(): string
    {
        return database_path('lookups/airlines.csv');
    }

    /**
     * ICAO, not IATA: it is the column Flight::airline() keys on, and the only
     * one of the two that is unique in the CSV (4,608 rows carry no IATA code).
     */
    protected function lookupKey(): string
    {
        return 'icao_code';
    }

    /**
     * The square icon mark, resolved by IATA code, or null when not downloaded.
     */
    protected function iconUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logoPath('icon'));
    }

    /**
     * The full wordmark logo, resolved by IATA code, or null when not downloaded.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logoPath('logo'));
    }

    /**
     * Public path to a downloaded logo variant, or null when the file is absent.
     */
    private function logoPath(string $variant): ?string
    {
        $iata = strtoupper((string) $this->iata_code);

        if ($iata === '' || ! file_exists(public_path("logos/airlines/{$variant}/{$iata}.png"))) {
            return null;
        }

        return "/logos/airlines/{$variant}/{$iata}.png";
    }
}
