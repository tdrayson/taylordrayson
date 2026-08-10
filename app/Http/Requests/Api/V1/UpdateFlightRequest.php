<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CabinClass;
use App\Enums\FlightReason;
use App\Models\Airline;
use App\Models\Airport;
use App\Rules\ExistsOnModel;
use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateFlightRequest extends FormRequest
{
    /**
     * Normalise unit strings to seconds/metres and pin occurred_at to one
     * wall-clock format, so a retry in a different format still hits the
     * idempotency key. Reformats only: never shift the zone.
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

        if ($this->has('occurred_at')) {
            $normalised['occurred_at'] = $this->normalisedOccurredAt($this->input('occurred_at'));
        }

        $this->merge($normalised);
    }

    /**
     * Reformat a Carbon-parseable occurred_at string to "Y-m-d H:i:s",
     * preserving its wall-clock components exactly as given. Falls back to
     * the original value when it can't be parsed, so the "date" rule can
     * reject it.
     */
    protected function normalisedOccurredAt(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date'],
            'flight_number' => ['sometimes', 'string', 'max:10'],
            'airline_icao' => ['sometimes', 'string', new ExistsOnModel(Airline::class, 'icao_code')],
            'origin_iata' => ['sometimes', 'string', 'size:3', new ExistsOnModel(Airport::class, 'iata_code')],
            'destination_iata' => ['sometimes', 'string', 'size:3', new ExistsOnModel(Airport::class, 'iata_code')],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'distance' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cabin_class' => ['sometimes', 'nullable', Rule::enum(CabinClass::class)],
            'reason' => ['sometimes', 'nullable', Rule::enum(FlightReason::class)],
            'departure_timezone' => ['sometimes', 'nullable', 'timezone'],
            'arrival_timezone' => ['sometimes', 'nullable', 'timezone'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
