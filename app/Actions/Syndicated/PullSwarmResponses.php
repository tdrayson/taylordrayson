<?php

namespace App\Actions\Syndicated;

use App\Data\SyndicatedResponseData;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Checkin;
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
        $responses = [
            ...array_map(fn (array $user): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Like,
                authorName: self::name($user),
                occurredAt: $checkin->occurred_at,
                sourceId: null,
                authorPhotoUrl: self::photo($user),
            ), self::likers($item)),

            ...array_map(fn (array $comment): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Reply,
                authorName: self::name($comment['user'] ?? []),
                occurredAt: Carbon::createFromTimestamp($comment['createdAt'] ?? $checkin->occurred_at->timestamp),
                sourceId: (string) $comment['id'],
                body: PortableText::fromPlainText((string) ($comment['text'] ?? '')),
                authorPhotoUrl: self::photo($comment['user'] ?? []),
            ), $item['comments']['items'] ?? []),
        ];

        ($this->reconcile)($checkin, Source::Swarm, $responses);
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
