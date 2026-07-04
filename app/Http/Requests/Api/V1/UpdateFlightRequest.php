<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFlightRequest extends FormRequest
{
    /**
     * Normalise flexible unit strings to canonical seconds/metres, leaving
     * unparseable values for the integer rules to reject.
     */
    protected function prepareForValidation(): void
    {
        $normalised = [];

        if ($this->has('duration')) {
            $normalised['duration'] = Units::seconds($this->input('duration')) ?? $this->input('duration');
        }

        if ($this->has('distance')) {
            $normalised['distance'] = Units::metres($this->input('distance')) ?? $this->input('distance');
        }

        $this->merge($normalised);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date'],
            'flight_number' => ['sometimes', 'string', 'max:10'],
            'airline_icao' => ['sometimes', 'string', 'exists:airlines,icao_code'],
            'origin_iata' => ['sometimes', 'string', 'size:3', 'exists:airports,iata_code'],
            'destination_iata' => ['sometimes', 'string', 'size:3', 'exists:airports,iata_code'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'distance' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cabin_class' => ['sometimes', 'nullable', 'string', 'max:30'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:100'],
            'departure_timezone' => ['sometimes', 'nullable', 'timezone'],
            'arrival_timezone' => ['sometimes', 'nullable', 'timezone'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
