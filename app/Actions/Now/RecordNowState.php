<?php

namespace App\Actions\Now;

use App\Http\Requests\Api\V1\StoreNowStateRequest;
use App\Support\StateStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Write a reading from the phone into state, one row per group.
 *
 * Each group merges rather than replaces, so a shortcut reporting only the
 * battery percentage leaves the charging flag another shortcut wrote.
 */
final class RecordNowState
{
    public function __construct(private readonly StateStore $state) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string> The groups actually written, for the response.
     */
    public function __invoke(array $payload): array
    {
        $observedAt = $this->observedAt($payload);
        $written = [];

        foreach (array_keys(StoreNowStateRequest::SCHEMA) as $group) {
            $values = $payload[$group] ?? null;

            if (! is_array($values) || $values === []) {
                continue;
            }

            $this->state->merge("now.{$group}", $values, $observedAt);
            $written[] = $group;
        }

        return $written;
    }

    /**
     * When the reading was taken. The phone's own timestamp is preferred, since
     * a delayed or retried send should still report when it was true; falling
     * back to now when the shortcut does not send one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function observedAt(array $payload): CarbonInterface
    {
        $sent = $payload['observed_at'] ?? null;

        return is_string($sent) ? CarbonImmutable::parse($sent) : CarbonImmutable::now();
    }
}
