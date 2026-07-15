<?php

namespace App\Models;

use App\Support\LookupCsv;
use Database\Factories\AirportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'city',
    'country',
    'latitude',
    'longitude',
])]
class Airport extends Model
{
    /** @use HasFactory<AirportFactory> */
    use HasFactory;

    use Sushi;

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
        'city' => 'string',
        'country' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['place'];

    /**
     * @return list<array<string, string|null>>
     */
    public function getRows(): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        return LookupCsv::from($this->lookupPath());
    }

    /**
     * Caching stays off under test. A cached empty row set would be written to
     * the shared cache file and later served to dev as real data.
     */
    protected function sushiShouldCache(): bool
    {
        return ! app()->environment('testing');
    }

    protected function sushiCacheReferencePath(): string
    {
        return $this->lookupPath();
    }

    private function lookupPath(): string
    {
        return database_path('lookups/airports.csv');
    }

    /**
     * Human label combining the city (or airport name) with its country as the
     * short ISO code, e.g. "London, GB".
     */
    protected function place(): Attribute
    {
        return Attribute::get(function (): string {
            $city = $this->city ?: $this->name;
            $country = $this->country ? strtoupper($this->country) : null;

            return collect([$city, $country])->filter()->implode(', ');
        });
    }
}
