<?php

namespace App\Queries;

use App\Support\StateStore;
use Carbon\CarbonImmutable;
use Locale;

/**
 * The ambient readings the Now page shows, as last sent from the phone. An
 * unwritten group comes back null; a written one is always shown however old,
 * with `observedAt` saying when it was true.
 */
final class NowState
{
    /**
     * Snake_case on the wire from Shortcuts, camelCase to the components.
     *
     * @var array<string, array<string, string>>
     */
    private const FIELDS = [
        // `device` is not sent; it comes from config and is attached below.
        'battery' => ['percent' => 'percent', 'charging' => 'charging', 'low_power' => 'lowPower'],
        // `high`/`low` are still accepted and stored, but nothing renders them.
        'weather' => ['condition' => 'condition', 'temp' => 'temp', 'humidity' => 'humidity', 'wind' => 'wind'],
        'location' => ['city' => 'city', 'state' => 'state', 'country_code' => 'countryCode', 'latitude' => 'latitude', 'longitude' => 'longitude', 'timezone' => 'timezone'],
        'rings' => ['move' => 'move', 'move_goal' => 'moveGoal', 'exercise' => 'exercise', 'exercise_goal' => 'exerciseGoal', 'stand' => 'stand', 'stand_goal' => 'standGoal', 'steps' => 'steps'],
    ];

    /**
     * Decimal places kept on a public coordinate. The Now map is a regional view
     * at zoom 5.6, so finer precision would only sit exposed in the page source.
     */
    private const COORDINATE_PLACES = 0;

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

        // Only alongside a real reading, or the tile claims a battery it was
        // never told about.
        if ($groups['battery'] !== null) {
            $groups['battery']['device'] = config('app.device');
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

        // Rounded here rather than per consumer, so the status bar and the
        // widget cannot disagree.
        foreach (['temp', 'humidity', 'wind'] as $reading) {
            if (isset($shaped[$reading])) {
                $shaped[$reading] = (int) round((float) $shaped[$reading]);
            }
        }

        // Dropped on the single path out to a public page; the stored value
        // stays precise.
        foreach (['latitude', 'longitude'] as $axis) {
            if (isset($shaped[$axis])) {
                $shaped[$axis] = (int) round((float) $shaped[$axis], self::COORDINATE_PLACES);
            }
        }

        // Derived so the phone sends one value rather than two that can disagree.
        if (isset($shaped['countryCode'])) {
            $shaped['country'] = Locale::getDisplayRegion('-'.$shaped['countryCode'], 'en');
        }

        // The "BST" abbreviation needs the tz database, so not in the browser.
        if (isset($shaped['timezone'])) {
            $shaped['tzAbbr'] = CarbonImmutable::now($shaped['timezone'])->format('T');
        }

        $shaped['observedAt'] = $entry['observedAt'] ?? $entry['updatedAt'];

        return $shaped;
    }
}
