<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlightRequest extends FormRequest
{
    /**
     * Normalise flexible unit strings ("2h 25m", "774 miles") to canonical
     * seconds/metres. Unparseable values are left as-is so the integer rules
     * reject them with a validation error.
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
            'occurred_at' => ['required', 'date'],
            'flight_number' => ['required', 'string', 'max:10'],
            'airline_icao' => ['required', 'string', 'exists:airlines,icao_code'],
            'origin_iata' => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
            'destination_iata' => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
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
