<?php

namespace App\Support;

use App\Models\State;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

/**
 * Read and write server-side ambient state.
 *
 * Values are stored JSON-encoded, so what goes in comes back the same type
 * without a companion type column. One row holds one group (`now.battery`),
 * because the things that arrive together are read together, and a per-group
 * row gives each group its own `observed_at` for display.
 */
class StateStore
{
    /**
     * Read a value, or $default when the key has never been written.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $state = State::query()->where('key', $key)->first();

        return $state === null ? $default : json_decode($state->value, true);
    }

    /**
     * Read a group's value alongside when it was observed, for UI that shows
     * how fresh a reading is. Null when the key has never been written.
     *
     * @return array{value: mixed, observedAt: ?string, updatedAt: string}|null
     */
    public function entry(string $key): ?array
    {
        $state = State::query()->where('key', $key)->first();

        if ($state === null) {
            return null;
        }

        return [
            'value' => json_decode($state->value, true),
            'observedAt' => $state->observed_at?->toIso8601String(),
            'updatedAt' => $state->updated_at->toIso8601String(),
        ];
    }

    /**
     * Read several keys in one query, for callers that need a whole set (the
     * status bar renders on every page, so it must not cost a query per group).
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{value: mixed, observedAt: ?string, updatedAt: string}>
     */
    public function entries(array $keys): array
    {
        return State::query()
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (State $state): array => [$state->key => [
                'value' => json_decode($state->value, true),
                'observedAt' => $state->observed_at?->toIso8601String(),
                'updatedAt' => $state->updated_at->toIso8601String(),
            ]])
            ->all();
    }

    /**
     * Overwrite a key outright.
     */
    public function put(string $key, mixed $value, ?CarbonInterface $observedAt = null): void
    {
        State::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value), 'observed_at' => $observedAt],
        );
    }

    /**
     * Merge into an array value, leaving keys the caller did not mention alone.
     *
     * This is what makes a partial send safe: a shortcut that reports only the
     * battery percentage cannot blank the charging flag written by another.
     *
     * @param  array<string, mixed>  $values
     */
    public function merge(string $key, array $values, ?CarbonInterface $observedAt = null): void
    {
        $existing = $this->get($key, []);

        $this->put($key, array_replace(Arr::wrap($existing), $values), $observedAt);
    }

    /**
     * Forget a key. Returns whether there was anything to forget.
     */
    public function forget(string $key): bool
    {
        return State::query()->where('key', $key)->delete() > 0;
    }
}
