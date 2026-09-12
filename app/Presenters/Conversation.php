<?php

namespace App\Presenters;

use App\Data\ConversationData;
use App\Data\ConversationItem;
use App\Models\Concerns\Timelineable;
use App\Queries\ReactionsFor;
use App\Support\InteractionTarget;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the response payload for one entry, merging the tables the frontend
 * should not have to know about.
 *
 * Mirrors CardPresenter: one static entry point returning one DTO.
 */
final class Conversation
{
    /**
     * The conversation to put on the page, or null for a target that takes no
     * interactions at all. Everything else carries one whether or not anybody
     * has responded: a page with no way to react is a page nobody can start.
     */
    public static function shownFor(Model $target, ?string $identity = null): ?ConversationData
    {
        return InteractionTarget::accepts($target) ? self::for($target, $identity) : null;
    }

    public static function for(Model $target, ?string $identity = null): ConversationData
    {
        // A page carries no timezone of its own; LocalTime then renders in
        // home time, same as it already does everywhere else.
        $timezone = $target instanceof Timelineable ? $target->timezone() : null;

        return new ConversationData(
            type: (string) InteractionTarget::keyFor($target),
            id: (int) $target->getKey(),
            url: rtrim((string) config('app.url'), '/').$target->url(),

            // On-site clicks only. A like sent by webmention is a person in the
            // thread below, not an anonymous +1 here, so nobody is counted twice.
            reactions: app(ReactionsFor::class)($target, $identity),

            responses: self::responses($target, $timezone),
        );
    }

    /**
     * Every response in one list, newest first, whatever kind it is and
     * whichever table it came from. Rendered in the entry's own timezone, so a
     * response can never sort or display ahead of what it responded to.
     *
     * @return list<ConversationItem>
     */
    private static function responses(Model $target, ?string $timezone): array
    {
        $items = [
            ...$target->comments()->approved()->get()->map(fn ($comment) => ConversationItem::fromComment($comment, $timezone))->all(),
            ...$target->webmentions()->approved()->get()->map(fn ($mention) => ConversationItem::fromWebmention($mention, $timezone))->all(),
            // No moderation state to filter on: these are written by the same
            // person the page belongs to, and the source is only ever an entry
            // that is already published.
            ...$target->mentions()->with('source')->get()->map(fn ($mention) => ConversationItem::fromMention($mention, $timezone))->all(),
            ...$target->syndicatedResponses()->approved()->get()->map(fn ($response) => ConversationItem::fromSyndicated($response, $timezone))->all(),
        ];

        usort($items, fn (ConversationItem $a, ConversationItem $b): int => $b->occurredAt <=> $a->occurredAt);

        return $items;
    }
}
