<?php

namespace App\Presenters;

use App\Data\ConversationData;
use App\Data\ConversationItem;
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
    public static function for(Model $target, ?string $identity = null): ConversationData
    {
        return new ConversationData(
            type: (string) InteractionTarget::keyFor($target),
            id: (int) $target->getKey(),
            url: rtrim((string) config('app.url'), '/').$target->url(),

            // On-site clicks only. A like sent by webmention is a person in the
            // thread below, not an anonymous +1 here, so nobody is counted twice.
            reactions: app(ReactionsFor::class)($target, $identity),

            responses: self::responses($target),
        );
    }

    /**
     * Every response in one list, newest first, whatever kind it is and
     * whichever table it came from.
     *
     * @return list<ConversationItem>
     */
    private static function responses(Model $target): array
    {
        $items = [
            ...$target->comments()->approved()->get()->map(ConversationItem::fromComment(...))->all(),
            ...$target->webmentions()->approved()->get()->map(ConversationItem::fromWebmention(...))->all(),
        ];

        usort($items, fn (ConversationItem $a, ConversationItem $b): int => $b->occurredAt <=> $a->occurredAt);

        return $items;
    }
}
