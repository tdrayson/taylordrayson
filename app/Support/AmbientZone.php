<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Where the phone last reported from, as a zone to stamp on entries that carry
 * no location of their own.
 *
 * Sleep, food, fuel and podcasts have nothing to derive a zone from, so without
 * this they render in home time however far from home they happened. No history
 * of readings is kept, so this only ever answers for entries arriving in real
 * time: a bulk import of old rows must not be stamped with today's location.
 */
final class AmbientZone
{
    /** How long a reading still describes where the phone is. */
    private const MAX_READING_AGE_HOURS = 36;

    /** How recently an entry must have happened to count as arriving live. */
    private const MAX_ENTRY_AGE_HOURS = 48;

    /**
     * Furthest ahead of UTC any zone runs. `occurred_at` is a wall-clock reading
     * carrying no offset, so one taken in Kiritimati reads as tomorrow against
     * server time and would otherwise look post-dated.
     */
    private const MAX_ZONE_LEAD_HOURS = 14;

    /** @var array{value: mixed, observedAt: ?string, updatedAt: string}|null */
    private ?array $reading = null;

    private bool $read = false;

    public function __construct(private readonly StateStore $state) {}

    /**
     * The zone to stamp on an entry at this local reading, or null to leave it
     * empty, which already renders as home.
     */
    public function forEntryAt(CarbonInterface $occurredAt): ?string
    {
        $now = CarbonImmutable::now();

        // Ahead of the reading, so importing history costs no queries at all.
        if (! $this->isLive($occurredAt, $now)) {
            return null;
        }

        $reading = $this->reading();

        if ($reading === null || ! is_array($reading['value'])) {
            return null;
        }

        $observedAt = CarbonImmutable::parse($reading['observedAt'] ?? $reading['updatedAt']);

        if ($observedAt->lessThan($now->subHours(self::MAX_READING_AGE_HOURS))) {
            return null;
        }

        $zone = $reading['value']['timezone'] ?? null;

        // Home is what an empty column already means, so writing it would only
        // hide which rows were ever positively placed.
        if (! is_string($zone) || $zone === EntryInstant::HOME) {
            return null;
        }

        return EntryInstant::zone($zone) === $zone ? $zone : null;
    }

    /** Whether the phone's current location can still describe this entry. */
    private function isLive(CarbonInterface $occurredAt, CarbonImmutable $now): bool
    {
        return $occurredAt->betweenIncluded(
            $now->subHours(self::MAX_ENTRY_AGE_HOURS),
            $now->addHours(self::MAX_ZONE_LEAD_HOURS),
        );
    }

    /**
     * Read once per request: a sync writing hundreds of rows must not cost a
     * query each.
     *
     * @return array{value: mixed, observedAt: ?string, updatedAt: string}|null
     */
    private function reading(): ?array
    {
        if (! $this->read) {
            $this->reading = $this->state->entry('now.location');
            $this->read = true;
        }

        return $this->reading;
    }
}
