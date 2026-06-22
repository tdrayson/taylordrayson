<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'country',
])]
class Airline extends Model
{
    /**
     * @var list<string>
     */
    protected $appends = ['icon_url', 'logo_url'];

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
