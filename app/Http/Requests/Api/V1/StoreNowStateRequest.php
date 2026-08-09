<?php

namespace App\Http\Requests\Api\V1;

use App\Queries\NowState;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * A reading of whatever the phone knows and the server cannot work out for
 * itself: battery, weather, where I am, today's rings so far. Everything else
 * on the Now page (sleep, podcast, photos, entry counts) is derived from data
 * already in the database and must not be sent.
 *
 * Every group is optional, so one shortcut can send the lot on a schedule while
 * another sends only `battery` when the phone is plugged in. Unknown keys are
 * rejected rather than ignored: a typo in a shortcut should announce itself,
 * not read as a working send that quietly changes nothing.
 */
class StoreNowStateRequest extends FormRequest
{
    /**
     * The complete set of groups and the fields each accepts.
     *
     * @var array<string, array<int, string>>
     */
    public const SCHEMA = [
        'battery' => ['percent', 'charging', 'low_power'],
        'weather' => ['condition', 'temp', 'high', 'low', 'humidity', 'wind'],
        // Field names mirror Apple's own labels so building the Shortcuts
        // dictionary is a straight copy. Note `state` is the administrative
        // area, which in the UK is "England" rather than the county; the county
        // arrives in Apple's "Add State" and is stored as `county`.
        'location' => [
            // Rendered publicly, coarsened where needed.
            'city', 'state', 'country_code', 'latitude', 'longitude', 'timezone',
            // Stored only. NowState builds the public payload from its own
            // allowlist, so these never reach a page: they are here for private
            // use of data the phone already has to hand. `name` is the place
            // itself ("David Lloyd Purley Way"), which iOS falls back to the
            // street address for when there is no named place, so it stays
            // private with the rest of them.
            'name', 'street', 'district', 'county', 'postcode',
        ],
        // `steps` here is today's count so far, which is ambient state like the
        // rings beside it. The historical daily series is a different thing and
        // belongs in the Vitals table (#67), fed from Rovi's own step records
        // when that exists; this value is never the source for that.
        'rings' => ['move', 'move_goal', 'exercise', 'exercise_goal', 'stand', 'stand_goal', 'steps'],
    ];

    /**
     * Take Shortcuts' output as it actually arrives rather than making the
     * shortcut do the tidying: booleans can come through as "true"/"false"
     * strings, a temperature carries its unit ("21°C") once it lands in a
     * dictionary, and a condition is human text ("Partly Cloudy") where the
     * widget wants a slug.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'battery' => $this->normalisedBattery(),
            'weather' => $this->normalisedWeather(),
            'location' => $this->normalisedLocation(),
            'rings' => $this->normalisedRings(),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalisedBattery(): ?array
    {
        $battery = $this->input('battery');

        if (! is_array($battery)) {
            return null;
        }

        // Readings arrive with their unit attached once they land in a
        // Shortcuts dictionary: "21°C", "62%", "8 mph".
        foreach (['charging', 'low_power'] as $flag) {
            if (is_string($battery[$flag] ?? null)) {
                $battery[$flag] = filter_var($battery[$flag], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $battery[$flag];
            }
        }

        return $battery;
    }

    /**
     * Coordinates are stored exactly as sent. They are coarsened on the way
     * out instead ({@see NowState}), so the precise position
     * stays available to anything private while never reaching a public page.
     *
     * @return array<string, mixed>|null
     */
    private function normalisedLocation(): ?array
    {
        $location = $this->input('location');

        if (! is_array($location)) {
            return null;
        }

        if (is_string($location['country_code'] ?? null)) {
            $location['country_code'] = strtoupper($location['country_code']);
        }

        return $location;
    }

    /**
     * Health values arrive from Shortcuts with their unit attached and, for
     * larger figures, a thousands separator: "137 kcal", "11,240 steps".
     *
     * @return array<string, mixed>|null
     */
    private function normalisedRings(): ?array
    {
        $rings = $this->input('rings');

        if (! is_array($rings)) {
            return null;
        }

        foreach (array_keys($rings) as $key) {
            $rings[$key] = $this->numberIn($rings[$key]) ?? $rings[$key];
        }

        return $rings;
    }

    /**
     * A number Shortcuts has stringified with its unit attached, or null when
     * the value is not that shape.
     *
     * Deliberately strict about what follows the digits: a unit ("kcal", "%",
     * "°C", "mph") or nothing at all. Grabbing the first number in any string
     * would read "2026-08-03" as 2026, turning a mis-mapped shortcut variable
     * into a plausible reading that passes validation. Anything else is left
     * alone so the rules reject it and the phone is told.
     */
    private function numberIn(mixed $value): int|float|null
    {
        if (! is_string($value)) {
            return null;
        }

        // Thousands separators first, so "11,240 steps" reads as one number.
        $cleaned = str_replace(',', '', trim($value));

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*[%\x{00B0}a-zA-Z\/\s.]*$/u', $cleaned, $match) !== 1) {
            return null;
        }

        return str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalisedWeather(): ?array
    {
        $weather = $this->input('weather');

        if (! is_array($weather)) {
            return null;
        }

        foreach (['temp', 'high', 'low', 'humidity', 'wind'] as $reading) {
            if (is_string($weather[$reading] ?? null) && preg_match('/-?\d+(\.\d+)?/', $weather[$reading], $match) === 1) {
                $weather[$reading] = (float) $match[0];
            }
        }

        // "Partly Cloudy" is what Shortcuts hands over; "partly-cloudy" is what
        // the widget's condition map is keyed by.
        if (is_string($weather['condition'] ?? null)) {
            $weather['condition'] = Str::slug($weather['condition']);
        }

        return $weather;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // When the reading was true in the world. A delayed or retried send
            // is still honest about when it was taken.
            'observed_at' => ['sometimes', 'date'],

            'battery' => ['sometimes', 'array'],
            'battery.percent' => ['sometimes', 'integer', 'between:0,100'],
            'battery.charging' => ['sometimes', 'boolean'],
            'battery.low_power' => ['sometimes', 'boolean'],

            'weather' => ['sometimes', 'array'],
            'weather.condition' => ['sometimes', 'string', 'max:40'],
            'weather.temp' => ['sometimes', 'numeric', 'between:-90,60'],
            'weather.high' => ['sometimes', 'numeric', 'between:-90,60'],
            'weather.low' => ['sometimes', 'numeric', 'between:-90,60'],
            'weather.humidity' => ['sometimes', 'numeric', 'between:0,100'],
            // Whatever unit the phone's locale reports, mph here. Nothing
            // renders it yet, so the unit is only decided if it ever does.
            'weather.wind' => ['sometimes', 'numeric', 'between:0,500'],

            'location' => ['sometimes', 'array'],
            'location.city' => ['sometimes', 'string', 'max:100'],
            'location.state' => ['sometimes', 'string', 'max:100'],
            'location.country_code' => ['sometimes', 'string', 'size:2'],
            'location.latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'location.longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'location.timezone' => ['sometimes', 'timezone'],
            'location.name' => ['sometimes', 'string', 'max:150'],
            'location.street' => ['sometimes', 'string', 'max:150'],
            'location.district' => ['sometimes', 'string', 'max:100'],
            'location.county' => ['sometimes', 'string', 'max:100'],
            'location.postcode' => ['sometimes', 'string', 'max:20'],

            'rings' => ['sometimes', 'array'],
            'rings.move' => ['sometimes', 'integer', 'between:0,20000'],
            'rings.move_goal' => ['sometimes', 'integer', 'between:1,20000'],
            'rings.exercise' => ['sometimes', 'integer', 'between:0,1440'],
            'rings.exercise_goal' => ['sometimes', 'integer', 'between:1,1440'],
            'rings.stand' => ['sometimes', 'integer', 'between:0,24'],
            'rings.stand_goal' => ['sometimes', 'integer', 'between:1,24'],
            'rings.steps' => ['sometimes', 'integer', 'between:0,200000'],
        ];
    }

    /**
     * Reject anything outside the schema, and an empty send, so a misbuilt
     * shortcut fails loudly on the phone instead of appearing to work.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $groups = array_keys(self::SCHEMA);
                $payload = $this->all();

                foreach (array_diff(array_keys($payload), [...$groups, 'observed_at']) as $unknown) {
                    $validator->errors()->add($unknown, "Unknown group `{$unknown}`. Expected one of: ".implode(', ', $groups).'.');
                }

                foreach (self::SCHEMA as $group => $fields) {
                    $sent = $payload[$group] ?? null;

                    if (! is_array($sent)) {
                        continue;
                    }

                    foreach (array_diff(array_keys($sent), $fields) as $unknown) {
                        $validator->errors()->add("{$group}.{$unknown}", "Unknown field `{$group}.{$unknown}`. Expected one of: ".implode(', ', $fields).'.');
                    }
                }

                if (empty(Arr::only($payload, $groups))) {
                    $validator->errors()->add('battery', 'Send at least one of: '.implode(', ', $groups).'.');
                }
            },
        ];
    }
}
