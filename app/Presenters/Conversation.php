<?php

namespace App\Presenters;

use App\Data\ConversationData;
use App\Data\ConversationItem;
use App\Models\Comment;
use App\Models\Concerns\Timelineable;
use App\Models\Mention;
use App\Models\SyndicatedResponse;
use App\Models\Webmention;
use App\Queries\ReactionsFor;
use App\Support\InteractionTarget;
use App\Support\VisitorIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Builds the response payload for one entry, merging the tables the frontend
 * should not have to know about.
 *
 * Mirrors CardPresenter: one static entry point returning one DTO.
 */
final class Conversation
{
    /**
     * The conversation to show this request, or null for a target it may not
     * respond to. Carried whether or not anybody has responded yet.
     *
     * @param  Model  $target  The entry or page the conversation belongs to.
     * @param  Request  $request  The visitor, whose unlock and reactions shape it.
     */
    public static function shownFor(Model $target, Request $request): ?ConversationData
    {
        return InteractionTarget::takesCommentsAndReactionsFrom($target, $request)
            ? self::for($target, VisitorIdentity::onTarget($request, $target))
            : null;
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

            takesWebmentions: InteractionTarget::takesMentions($target),
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
            ...$target->comments()->approved()->get()->map(fn (Comment $comment): ConversationItem => ConversationItem::fromComment($comment, $timezone))->all(),
            ...$target->webmentions()->approved()->get()->map(fn (Webmention $mention): ConversationItem => ConversationItem::fromWebmention($mention, $timezone))->all(),
            // No moderation state to filter on: these are written by the same
            // person the page belongs to, and the source is only ever an entry
            // that is already published.
            ...$target->mentions()->with('source')->get()->map(fn (Mention $mention): ConversationItem => ConversationItem::fromMention($mention, $timezone))->all(),
            ...$target->syndicatedResponses()->approved()->get()->map(fn (SyndicatedResponse $response): ConversationItem => ConversationItem::fromSyndicated($response, $timezone))->all(),
        ];

        usort($items, fn (ConversationItem $a, ConversationItem $b): int => $b->occurredAt <=> $a->occurredAt);

        return $items;
    }
}
