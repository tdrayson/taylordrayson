<?php

namespace App\Queries;

use App\Support\StateStore;
use Carbon\CarbonImmutable;
use Locale;

/**
 * The ambient readings the Now page shows, as last sent from the phone.
 *
 * A group that has never been written comes back null so the widget falls back
 * to its own placeholder. A group that has been written is always shown, however
 * old: these are readings, not predictions, and `observedAt` says when each was
 * true so a stale one can say so rather than being hidden.
 */
final class NowState
{
    /**
     * Snake_case on the wire from Shortcuts, camelCase to the components.
     *
     * @var array<string, array<string, string>>
     */
    private const FIELDS = [
        'battery' => ['percent' => 'percent', 'charging' => 'charging', 'low_power' => 'lowPower', 'device' => 'device'],
        'weather' => ['condition' => 'condition', 'temp' => 'temp', 'high' => 'high', 'low' => 'low'],
        'location' => ['city' => 'city', 'state' => 'state', 'country_code' => 'countryCode', 'latitude' => 'latitude', 'longitude' => 'longitude', 'timezone' => 'timezone'],
        'rings' => ['move' => 'move', 'move_goal' => 'moveGoal', 'exercise' => 'exercise', 'exercise_goal' => 'exerciseGoal', 'stand' => 'stand', 'stand_goal' => 'standGoal', 'steps' => 'steps'],
    ];

    /**
     * Decimal places kept on a public coordinate: ~1.1km, a town not a house.
     */
    private const COORDINATE_PLACES = 2;

    public function __construct(private readonly StateStore $state) {}

    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function __invoke(): array
    {
        $keys = array_map(fn (string $group): string => "now.{$group}", array_keys(self::FIELDS));
        $entries = $this->state->entries($keys);
        $groups = [];

        foreach (self::FIELDS as $group => $map) {
            $groups[$group] = $this->group($entries["now.{$group}"] ?? null, $map);
        }

        return $groups;
    }

    /**
     * @param  array{value: mixed, observedAt: ?string, updatedAt: string}|null  $entry
     * @param  array<string, string>  $map
     * @return array<string, mixed>|null
     */
    private function group(?array $entry, array $map): ?array
    {
        if ($entry === null || ! is_array($entry['value'])) {
            return null;
        }

        $shaped = [];

        foreach ($map as $sent => $prop) {
            if (array_key_exists($sent, $entry['value'])) {
                $shaped[$prop] = $entry['value'][$sent];
            }
        }

        if ($shaped === []) {
            return null;
        }

        // Coordinates are stored exactly but never leave the server that way:
        // this payload is rendered on a public page, so precision is dropped on
        // the single path out. The stored value stays precise for private use.
        foreach (['latitude', 'longitude'] as $axis) {
            if (isset($shaped[$axis])) {
                $shaped[$axis] = round((float) $shaped[$axis], self::COORDINATE_PLACES);
            }
        }

        // A country code is enough: intl turns it into a display name, so the
        // phone sends one value rather than two that can disagree.
        if (isset($shaped['countryCode'])) {
            $shaped['country'] = Locale::getDisplayRegion('-'.$shaped['countryCode'], 'en');
        }

        // The status bar shows the zone as an abbreviation ("BST"), which needs
        // the tz database, so it is resolved here rather than in the browser.
        if (isset($shaped['timezone'])) {
            $shaped['tzAbbr'] = CarbonImmutable::now($shaped['timezone'])->format('T');
        }

        $shaped['observedAt'] = $entry['observedAt'] ?? $entry['updatedAt'];

        return $shaped;
    }
}
