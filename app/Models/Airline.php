<?php

namespace App\Models;

use App\Support\LookupCsv;
use Database\Factories\AirlineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

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
        'country' => 'string',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['icon_url', 'logo_url'];

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
        return database_path('lookups/airlines.csv');
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
