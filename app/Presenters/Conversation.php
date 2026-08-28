<?php

namespace App\Presenters;

use App\Data\ConversationData;
use App\Data\ConversationItem;
use App\Data\ReactionBucket;
use App\Enums\ReactionType;
use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Webmention;
use App\Queries\ReactionsFor;
use App\Support\InteractionTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds the response payload for one entry, merging the three tables the
 * frontend should not have to know about.
 *
 * Mirrors CardPresenter: one static entry point returning one DTO.
 */
final class Conversation
{
    /** Mentions that read as a response, and so belong in the thread. */
    private const THREADED = [WebmentionKind::Reply->value, WebmentionKind::Rsvp->value];

    public static function for(Model $target, ?string $identity = null): ConversationData
    {
        $mentions = Webmention::query()
            ->approved()
            ->whereMorphedTo('target', $target)
            ->orderBy('published_at')
            ->get();

        $replies = $mentions->filter(fn (Webmention $m): bool => in_array($m->kind, self::THREADED, true));

        $items = [
            ...Comment::query()
                ->approved()
                ->whereMorphedTo('commentable', $target)
                ->orderBy('created_at')
                ->get()
                ->map(ConversationItem::fromComment(...))
                ->all(),
            ...$replies->map(ConversationItem::fromWebmention(...))->all(),
        ];

        usort($items, fn (ConversationItem $a, ConversationItem $b): int => $a->occurredAt <=> $b->occurredAt);

        return new ConversationData(
            type: (string) InteractionTarget::keyFor($target),
            id: (int) $target->getKey(),
            url: rtrim((string) config('app.url'), '/').$target->url(),
            reactions: [
                ...app(ReactionsFor::class)($target, $identity),
                ...self::unofferedEmoji($mentions),
            ],
            replies: $items,
            mentions: $mentions
                ->reject(fn (Webmention $m): bool => in_array($m->kind, self::THREADED, true))
                // A reacji is already counted in the bar above, so showing it
                // here as well would report the same person twice.
                ->reject(fn (Webmention $m): bool => $m->kind === WebmentionKind::Reacji->value)
                ->map(ConversationItem::fromWebmention(...))
                ->values()
                ->all(),
        );
    }

    /**
     * Reacji whose emoji is not one of the five we offer.
     *
     * Kept as buckets of their own rather than dropped or bent into the
     * nearest match: someone sending 🚀 meant 🚀, and the count is still true.
     *
     * @param  Collection<int, Webmention>  $mentions
     * @return list<ReactionBucket>
     */
    private static function unofferedEmoji(Collection $mentions): array
    {
        return $mentions
            ->where('kind', WebmentionKind::Reacji->value)
            ->filter(fn (Webmention $m): bool => ReactionType::fromEmoji((string) $m->content) === null)
            ->groupBy('content')
            ->map(fn ($group, string $emoji): ReactionBucket => new ReactionBucket(
                key: $emoji,
                emoji: $emoji,
                label: 'Reacted '.$emoji,
                count: $group->count(),
                // Nothing to toggle: it belongs to whoever sent it.
                mine: false,
            ))
            ->values()
            ->all();
    }
}
