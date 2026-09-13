<?php

namespace App\Actions\Mentions;

use App\Models\Mention;
use App\Support\InteractionTarget;
use App\Support\OutboundLinks;
use Illuminate\Database\Eloquent\Model;

/**
 * Rewrites a source's mentions to match the links its body currently carries.
 *
 * Runs in the request rather than on the queue: this is a derivation from a
 * column that was just written, so there is nothing to fetch, nothing to retry
 * and no window in which the page could render without it.
 */
final class SyncMentions
{
    public function __construct(private readonly ResolveInternalTarget $resolve) {}

    /**
     * Sync the mentions a source records. A private source is left exactly as it
     * stood: the history stays, and nothing new is written while it is locked.
     *
     * @param  Model  $source  The entry or page whose links were just saved.
     */
    public function __invoke(Model $source): void
    {
        if (InteractionTarget::keepsMentions($source) && ! InteractionTarget::sendsMentions($source)) {
            return;
        }

        $linked = $this->linked($source);
        $existing = Mention::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->get();

        foreach ($existing as $mention) {
            $key = $this->key($mention);

            // A target gone private keeps the mention it already had, as long
            // as the link to it is still there.
            if (array_key_exists($key, $linked) && InteractionTarget::keepsMentions($linked[$key])) {
                unset($linked[$key]);
            } else {
                $mention->delete();
            }
        }

        foreach ($linked as $target) {
            if (! InteractionTarget::takesMentions($target)) {
                continue;
            }

            Mention::query()->create([
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'target_type' => $target->getMorphClass(),
                'target_id' => $target->getKey(),
            ]);
        }
    }

    /**
     * Every entry this source links to, whatever its status, keyed by the pair
     * the table is unique on so the diff is two array lookups rather than a
     * nested loop.
     *
     * A draft source has no links anyone can follow, so it wants no mentions
     * at all: unpublishing removes them, republishing writes them back.
     *
     * @return array<string, Model>
     */
    private function linked(Model $source): array
    {
        if (! InteractionTarget::sendsMentions($source)) {
            return [];
        }

        $targets = [];

        foreach (OutboundLinks::internalPathsFor($source) as $path) {
            $target = $this->resolve->anyStatus($path);

            // Self-links are dropped rather than stored and filtered later: an
            // entry is not a mention of itself, and the conversation would
            // otherwise show a post responding to its own page.
            if ($target === null || $this->isSame($source, $target)) {
                continue;
            }

            $targets[$target->getMorphClass().':'.$target->getKey()] = $target;
        }

        return $targets;
    }

    private function key(Mention $mention): string
    {
        return $mention->target_type.':'.$mention->target_id;
    }

    private function isSame(Model $source, Model $target): bool
    {
        return $source->getMorphClass() === $target->getMorphClass()
            && $source->getKey() === $target->getKey();
    }
}
