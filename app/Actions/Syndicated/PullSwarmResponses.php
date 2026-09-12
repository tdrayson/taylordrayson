<?php

namespace App\Actions\Syndicated;

use App\Data\SyndicatedResponseData;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Checkin;
use App\Support\EntryInstant;
use App\Support\PortableText;
use Carbon\Carbon;

/**
 * The likes and comments Swarm reported for one check-in.
 *
 * Both arrive inside the check-in payload the sync already fetched, so this
 * takes the item rather than making a request of its own.
 */
final class PullSwarmResponses
{
    public function __construct(private readonly ReconcileResponses $reconcile) {}

    /**
     * @param  array<string, mixed>  $item  One check-in item from the Foursquare payload.
     */
    public function __invoke(Checkin $checkin, array $item): void
    {
        $likers = self::likers($item);
        $likesAmbiguous = self::likesAmbiguous($item, $likers);

        // occurred_at is a wall-clock reading, not an instant: a like has no
        // timestamp of its own, so it borrows the check-in's, converted via
        // the check-in's own timezone. A comment missing its own createdAt
        // falls back to the same converted instant.
        $checkinOccurredAt = EntryInstant::utc($checkin->occurred_at, $checkin->timezone()) ?? $checkin->occurred_at;

        $responses = [
            ...($likesAmbiguous ? [] : array_map(fn (array $user): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Like,
                authorName: self::name($user),
                occurredAt: $checkinOccurredAt,
                sourceId: null,
                authorPhotoUrl: self::photo($user),
            ), $likers)),

            ...array_map(fn (array $comment): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Reply,
                authorName: self::name($comment['user'] ?? []),
                occurredAt: isset($comment['createdAt'])
                    ? Carbon::createFromTimestamp($comment['createdAt'])
                    : $checkinOccurredAt,
                sourceId: (string) $comment['id'],
                body: PortableText::fromPlainText((string) ($comment['text'] ?? '')),
                authorPhotoUrl: self::photo($comment['user'] ?? []),
            ), self::comments($item)),
        ];

        ($this->reconcile)($checkin, Source::Swarm, $responses, $likesAmbiguous ? [WebmentionKind::Like] : []);
    }

    /**
     * A positive count with no resolvable liker items means the likers aren't
     * visible to us, not that nobody liked it. Strava has the same "null means
     * we don't know" guard for a failed request; this is Swarm's equivalent.
     *
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $likers
     */
    private static function likesAmbiguous(array $item, array $likers): bool
    {
        return ($item['likes']['count'] ?? 0) > 0 && $likers === [];
    }

    /**
     * Comments without an id cannot be keyed on, so they cannot be kept in
     * step with the source on future runs. A wrong row is worse than missing.
     *
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    private static function comments(array $item): array
    {
        return array_filter($item['comments']['items'] ?? [], fn (array $comment): bool => isset($comment['id']));
    }

    /**
     * Swarm groups likers by relationship (friends, others), which is a fact
     * about them rather than about the like.
     *
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    private static function likers(array $item): array
    {
        $groups = $item['likes']['groups'] ?? [];

        return array_merge(...array_map(fn (array $group): array => $group['items'] ?? [], $groups)) ?: [];
    }

    /** @param array<string, mixed> $user */
    private static function name(array $user): string
    {
        return $user['displayName']
            ?? trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? ''))
            ?: 'Someone on Swarm';
    }

    /** @param array<string, mixed> $user */
    private static function photo(array $user): ?string
    {
        $photo = $user['photo'] ?? null;

        return isset($photo['prefix'], $photo['suffix'])
            ? $photo['prefix'].'original'.$photo['suffix']
            : null;
    }
}
