<?php

namespace App\Presenters;

use App\Data\ConversationData;
use App\Data\ConversationItem;
use App\Data\FaceData;
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
 *
 * Responses are split by weight rather than listed together, which is what a
 * facepile is for: a like shown at the same size as a paragraph makes the list
 * long to browse and buries the paragraph.
 */
final class Conversation
{
    /** Mentions that read as a response, and so belong in the thread. */
    private const THREADED = [WebmentionKind::Reply->value, WebmentionKind::Rsvp->value];

    /** Mentions that are a gesture, and so belong in the facepile. */
    private const FACES = [WebmentionKind::Like->value, WebmentionKind::Reacji->value];

    public static function for(Model $target, ?string $identity = null): ConversationData
    {
        $mentions = Webmention::query()
            ->approved()
            ->whereMorphedTo('target', $target)
            ->orderBy('published_at')
            ->get();

        return new ConversationData(
            type: (string) InteractionTarget::keyFor($target),
            id: (int) $target->getKey(),
            url: rtrim((string) config('app.url'), '/').$target->url(),

            // On-site clicks only. An incoming like is a face below rather than
            // an anonymous +1 here, so nobody is counted in two places.
            reactions: app(ReactionsFor::class)($target, $identity),

            faces: self::of($mentions, self::FACES)
                ->map(FaceData::fromWebmention(...))
                ->values()
                ->all(),

            replies: self::thread($target, $mentions),

            mentions: $mentions
                ->reject(fn (Webmention $m): bool => in_array($m->kind, [...self::THREADED, ...self::FACES], true))
                ->map(ConversationItem::fromWebmention(...))
                ->values()
                ->all(),
        );
    }

    /**
     * Comments and reply-shaped mentions in one list, oldest first, so a reply
     * written on somebody's own site sits in the conversation, not beside it.
     *
     * @param  Collection<int, Webmention>  $mentions
     * @return list<ConversationItem>
     */
    private static function thread(Model $target, Collection $mentions): array
    {
        $items = [
            ...Comment::query()
                ->approved()
                ->whereMorphedTo('commentable', $target)
                ->orderBy('created_at')
                ->get()
                ->map(ConversationItem::fromComment(...))
                ->all(),
            ...self::of($mentions, self::THREADED)->map(ConversationItem::fromWebmention(...))->all(),
        ];

        usort($items, fn (ConversationItem $a, ConversationItem $b): int => $a->occurredAt <=> $b->occurredAt);

        return $items;
    }

    /**
     * @param  Collection<int, Webmention>  $mentions
     * @param  list<string>  $kinds
     * @return Collection<int, Webmention>
     */
    private static function of(Collection $mentions, array $kinds): Collection
    {
        return $mentions->filter(fn (Webmention $m): bool => in_array($m->kind, $kinds, true));
    }
}
