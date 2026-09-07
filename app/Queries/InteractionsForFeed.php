<?php

namespace App\Queries;

use App\Data\ReactionBucket;
use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\Webmention;
use App\Support\InteractionTarget;
use App\Support\VisitorIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The reaction bar for a whole page of entries at once.
 *
 * `ReactionsFor` answers for one target and runs two queries doing it, which is
 * fine under an entry and is a hundred queries down a feed. This answers for
 * every entry on the page in four: reactions, this visitor's own reactions,
 * comments, and mentions by kind.
 *
 * Keyed `type:id`, matching what the reaction endpoint is addressed by, so the
 * feed can look a card's row up without knowing anything about models.
 */
final class InteractionsForFeed
{
    /**
     * @param  Collection<int, Model>  $targets
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(Collection $targets, ?Request $request = null): array
    {
        $keyed = $targets
            ->filter(fn (Model $model): bool => InteractionTarget::accepts($model))
            ->keyBy(fn (Model $model): string => $model::class.':'.$model->getKey());

        if ($keyed->isEmpty()) {
            return [];
        }

        $classes = $keyed->map(fn (Model $m): string => $m::class)->unique()->values()->all();
        $ids = $keyed->map(fn (Model $m): int|string => $m->getKey())->unique()->values()->all();

        // Hashed per target rather than per visitor, so a page needs the whole
        // set: one hash for each entry on it, matched back by the row's own
        // type and id rather than by the key.
        $identities = $request === null
            ? []
            : $keyed->map(fn (Model $m): string => VisitorIdentity::onTarget($request, $m))->values()->all();

        $counts = $this->reactionCounts($classes, $ids);
        $mine = $this->myReactions($classes, $ids, $identities);
        $comments = $this->commentCounts($classes, $ids);
        $mentions = $this->mentionCounts($classes, $ids);

        $rows = [];

        foreach ($keyed as $pair => $model) {
            $rows[InteractionTarget::keyFor($model).':'.$model->getKey()] = [
                'reactions' => array_map(
                    fn (ReactionType $type): array => ReactionBucket::fromType(
                        $type,
                        (int) ($counts[$pair][$type->value] ?? 0),
                        in_array($type->value, $mine[$pair] ?? [], strict: true),
                    )->toArray(),
                    ReactionType::cases(),
                ),
                // Counted by kind, matching the entry page: every interaction
                // lands in exactly one figure, so the parts sum to the whole.
                'replyCount' => (int) ($comments[$pair] ?? 0) + (int) ($mentions[$pair][WebmentionKind::Reply->value] ?? 0),
                'likeCount' => (int) ($mentions[$pair][WebmentionKind::Like->value] ?? 0)
                    + (int) ($mentions[$pair][WebmentionKind::Reacji->value] ?? 0),
                'repostCount' => (int) ($mentions[$pair][WebmentionKind::Repost->value] ?? 0),
                'bookmarkCount' => (int) ($mentions[$pair][WebmentionKind::Bookmark->value] ?? 0),
                'rsvpCount' => (int) ($mentions[$pair][WebmentionKind::Rsvp->value] ?? 0),
                'mentionCount' => (int) ($mentions[$pair][WebmentionKind::Mention->value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $classes
     * @param  list<int|string>  $ids
     * @return array<string, array<string, int>>
     */
    private function reactionCounts(array $classes, array $ids): array
    {
        $rows = Reaction::query()
            ->toBase()
            ->selectRaw('reactable_type, reactable_id, type, count(*) as total')
            ->whereIn('reactable_type', $classes)
            ->whereIn('reactable_id', $ids)
            ->groupBy('reactable_type', 'reactable_id', 'type')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->reactable_type.':'.$row->reactable_id][$row->type] = (int) $row->total;
        }

        return $out;
    }

    /**
     * @param  list<string>  $classes
     * @param  list<int|string>  $ids
     * @param  list<string>  $identities
     * @return array<string, list<string>>
     */
    private function myReactions(array $classes, array $ids, array $identities): array
    {
        if ($identities === []) {
            return [];
        }

        $rows = Reaction::query()
            ->toBase()
            ->select('reactable_type', 'reactable_id', 'type')
            ->whereIn('identity_key', $identities)
            ->whereIn('reactable_type', $classes)
            ->whereIn('reactable_id', $ids)
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->reactable_type.':'.$row->reactable_id][] = (string) $row->type;
        }

        return $out;
    }

    /**
     * @param  list<string>  $classes
     * @param  list<int|string>  $ids
     * @return array<string, int>
     */
    private function commentCounts(array $classes, array $ids): array
    {
        $rows = Comment::query()
            ->toBase()
            ->selectRaw('commentable_type, commentable_id, count(*) as total')
            ->where('status', CommentStatus::Approved->value)
            ->whereIn('commentable_type', $classes)
            ->whereIn('commentable_id', $ids)
            ->groupBy('commentable_type', 'commentable_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->commentable_type.':'.$row->commentable_id] = (int) $row->total;
        }

        return $out;
    }

    /**
     * Mentions by kind.
     *
     * @param  list<string>  $classes
     * @param  list<int|string>  $ids
     * @return array<string, array<string, int>>
     */
    private function mentionCounts(array $classes, array $ids): array
    {
        $rows = Webmention::query()
            ->toBase()
            ->selectRaw('target_type, target_id, kind, count(*) as total')
            ->where('status', CommentStatus::Approved->value)
            ->whereIn('target_type', $classes)
            ->whereIn('target_id', $ids)
            ->groupBy('target_type', 'target_id', 'kind')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->target_type.':'.$row->target_id][(string) $row->kind] = (int) $row->total;
        }

        return $out;
    }
}
