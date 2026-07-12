<?php

namespace App\Models;

use App\Support\CsvLookupRows;
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

    /**
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

        return CsvLookupRows::from(base_path('data/airports.csv'));
    }

    protected function sushiShouldCache(): bool
    {
        return ! app()->environment('testing');
    }

    protected function sushiCacheReferencePath(): string
    {
        return base_path('data/airports.csv');
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
