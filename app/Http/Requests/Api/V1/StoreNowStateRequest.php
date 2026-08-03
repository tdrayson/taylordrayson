<?php

namespace App\Http\Requests\Api\V1;

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
        'battery' => ['percent', 'charging', 'low_power', 'device'],
        'weather' => ['condition', 'temp', 'high', 'low'],
        'location' => ['city', 'latitude', 'longitude', 'timezone'],
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

        foreach (['charging', 'low_power'] as $flag) {
            if (is_string($battery[$flag] ?? null)) {
                $battery[$flag] = filter_var($battery[$flag], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $battery[$flag];
            }
        }

        return $battery;
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

        foreach (['temp', 'high', 'low'] as $reading) {
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
            'battery.device' => ['sometimes', 'string', 'max:50'],

            'weather' => ['sometimes', 'array'],
            'weather.condition' => ['sometimes', 'string', 'max:40'],
            'weather.temp' => ['sometimes', 'numeric', 'between:-90,60'],
            'weather.high' => ['sometimes', 'numeric', 'between:-90,60'],
            'weather.low' => ['sometimes', 'numeric', 'between:-90,60'],

            'location' => ['sometimes', 'array'],
            'location.city' => ['sometimes', 'string', 'max:100'],
            'location.latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'location.longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'location.timezone' => ['sometimes', 'timezone'],

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
