<?php

namespace App\Queries;

use App\Data\ReactionBucket;
use App\Enums\ReactionType;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * The reaction bar for one target: every offered emoji in a fixed order, with
 * its count and whether this visitor is in it.
 *
 * Every bucket is returned, including the empty ones, so the bar renders as a
 * stable row of choices rather than appearing an emoji at a time.
 */
final class ReactionsFor
{
    /**
     * @return list<ReactionBucket>
     */
    public function __invoke(Model $target, ?string $identity = null): array
    {
        // toBase() throughout: `type` is a cast enum, and Eloquent's pluck()
        // would hand back enum instances, which cannot be used as array keys.
        $counts = self::scoped($target)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $mine = $identity === null
            ? []
            : self::scoped($target)->where('identity_key', $identity)->pluck('type')->all();

        return array_map(
            fn (ReactionType $type): ReactionBucket => ReactionBucket::fromType(
                $type,
                (int) ($counts[$type->value] ?? 0),
                in_array($type->value, $mine, strict: true),
            ),
            ReactionType::cases(),
        );
    }

    private static function scoped(Model $target): Builder
    {
        return Reaction::query()
            ->where('reactable_type', $target->getMorphClass())
            ->where('reactable_id', $target->getKey())
            ->toBase();
    }
}
