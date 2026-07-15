<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Airline;
use App\Models\Airport;
use App\Rules\ExistsOnModel;
use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateFlightRequest extends FormRequest
{
    /**
     * Normalise flexible unit strings to canonical seconds/metres, and
     * canonicalise occurred_at to a fixed wall-clock format so retried
     * submissions with a different timestamp format still match the
     * natural-key lookup used for idempotency. occurred_at is local
     * wall-clock time, so this must only reformat the string, never shift
     * it to another timezone. Unparseable values are left for the relevant
     * rules to reject.
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
            'cabin_class' => ['sometimes', 'nullable', 'string', 'max:30'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:100'],
            'departure_timezone' => ['sometimes', 'nullable', 'timezone'],
            'arrival_timezone' => ['sometimes', 'nullable', 'timezone'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
