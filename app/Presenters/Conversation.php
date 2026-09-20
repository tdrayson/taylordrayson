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
        $timezone = self::timezoneOf($target);

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
     * One comment as the thread's other items are shaped, so a comment just
     * written can be added to the list without the client building it.
     */
    public static function item(Comment $comment, Model $target): ConversationItem
    {
        return ConversationItem::fromComment($comment, self::timezoneOf($target));
    }

    /**
     * A page carries no timezone of its own; LocalTime then renders in home
     * time, same as it already does everywhere else.
     */
    private static function timezoneOf(Model $target): ?string
    {
        return $target instanceof Timelineable ? $target->timezone() : null;
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
        $mentions = $target->webmentions()->approved()->get();

        // A salmention names the page it was read from, not a row id, so the
        // mention that carried it is looked up here rather than joined.
        $carriedBy = $mentions->whereNull('parent_source_url')->pluck('id', 'source_url');

        $items = [
            ...$target->comments()->approved()->get()->map(fn (Comment $comment): ConversationItem => ConversationItem::fromComment($comment, $timezone))->all(),
            ...$mentions->map(fn (Webmention $mention): ConversationItem => ConversationItem::fromWebmention(
                $mention,
                $timezone,
                $mention->parent_source_url === null ? null : $carriedBy[$mention->parent_source_url] ?? null,
            ))->all(),
            // No moderation state to filter on: these are written by the same
            // person the page belongs to. A private source is named, as its title
            // already is publicly, and never quoted.
            ...$target->mentions()->with('source')->get()->map(fn (Mention $mention): ConversationItem => ConversationItem::fromMention($mention, $timezone))->all(),
            ...$target->syndicatedResponses()->approved()->get()->map(fn (SyndicatedResponse $response): ConversationItem => ConversationItem::fromSyndicated($response, $timezone))->all(),
        ];

        usort($items, fn (ConversationItem $a, ConversationItem $b): int => $b->occurredAt <=> $a->occurredAt);

        return $items;
    }
}
