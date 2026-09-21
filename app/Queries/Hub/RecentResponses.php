<?php

namespace App\Queries\Hub;

use App\Data\Hub\ResponseItem;
use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\SyndicatedResponse;
use App\Models\Webmention;
use App\Support\InteractionTarget;
use App\Support\PortableText;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * The latest things people have said, across the four tables that hold them.
 *
 * Four small selects merged here rather than a SQL union: the column names
 * differ per table, and at six rows four indexed queries cost nothing.
 */
final class RecentResponses
{
    /**
     * Per-reader fetch window, ahead of collapsing. Wider than any realistic
     * $limit so a burst of same-day likes on one target cannot crowd an
     * older, distinct response out of the query before collapse() runs.
     */
    private const WINDOW = 50;

    /**
     * @param  int  $limit  How many rows to return, after collapsing.
     * @param  Carbon|null  $seenAt  The previous visit, for marking what is new.
     * @return list<ResponseItem>
     */
    public function __invoke(int $limit, ?Carbon $seenAt = null): array
    {
        $rows = [
            ...$this->comments(),
            ...$this->mentions(),
            ...$this->reactions(),
            ...$this->syndicated(),
        ];

        usort($rows, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        $collapsed = $this->collapse($rows);

        return array_map(
            fn (array $row): ResponseItem => $this->item($row, $seenAt),
            array_slice($collapsed, 0, $limit),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function comments(): array
    {
        return Comment::query()
            ->approved()
            ->with('commentable')
            ->latest('created_at')
            ->limit(self::WINDOW)
            ->get()
            ->map(fn (Comment $comment): array => [
                'id' => 'comment-'.$comment->id,
                'kind' => 'comment',
                'icon' => 'Comment01Icon',
                'who' => $comment->author_name,
                'verb' => 'commented on',
                'target' => $comment->commentable,
                'body' => PortableText::plainText($comment->body),
                'at' => $comment->created_at,
                'collapses' => false,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mentions(): array
    {
        return Webmention::query()
            ->approved()
            ->with('target')
            ->latest('created_at')
            ->limit(self::WINDOW)
            ->get()
            ->map(function (Webmention $mention): array {
                $kind = $mention->kind();
                $reply = $kind === WebmentionKind::Reply;

                return [
                    'id' => 'mention-'.$mention->id,
                    'kind' => $reply ? 'reply' : 'mention',
                    'icon' => $reply ? 'MailReply01Icon' : 'Link04Icon',
                    'who' => $mention->author_name ?: $mention->author_host ?: 'Someone',
                    'verb' => $reply ? 'replied on' : 'linked to',
                    'target' => $mention->target,
                    'body' => $reply ? PortableText::plainText($mention->content ?? []) : null,
                    'at' => $mention->created_at,
                    // A like or repost sent by webmention is one person, named,
                    // so it reads as its own row rather than a tally.
                    'collapses' => in_array($kind, [WebmentionKind::Like, WebmentionKind::Repost], true),
                ];
            })
            ->all();
    }

    /**
     * On-site reactions, which carry no name: identity is an IP hash, so these
     * are always a tally and never "someone said".
     *
     * @return list<array<string, mixed>>
     */
    private function reactions(): array
    {
        return Reaction::query()
            ->with('reactable')
            ->latest('created_at')
            ->limit(self::WINDOW)
            ->get()
            ->map(fn (Reaction $reaction): array => [
                'id' => 'reaction-'.$reaction->id,
                'kind' => 'like',
                'icon' => 'HeartIcon',
                'who' => null,
                'verb' => 'reacted to',
                'target' => $reaction->reactable,
                'body' => null,
                'at' => $reaction->created_at,
                'collapses' => true,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function syndicated(): array
    {
        return SyndicatedResponse::query()
            ->approved()
            ->with('target')
            ->latest('occurred_at')
            ->limit(self::WINDOW)
            ->get()
            ->map(function (SyndicatedResponse $response): array {
                $reply = $response->kind === WebmentionKind::Reply;

                return [
                    'id' => 'syndicated-'.$response->id,
                    'kind' => $reply ? 'reply' : 'like',
                    'icon' => $reply ? 'MailReply01Icon' : 'ThumbsUpIcon',
                    'who' => $response->author_name,
                    'verb' => $reply ? 'replied on' : 'gave kudos on',
                    'target' => $response->target,
                    'body' => $reply ? PortableText::plainText($response->body ?? []) : null,
                    'at' => $response->occurred_at,
                    'collapses' => ! $reply,
                ];
            })
            ->all();
    }

    /**
     * Fold likes on the same entry on the same day into one row naming up to
     * three people. Comments, replies and mentions never collapse.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function collapse(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! $row['collapses'] || $row['target'] === null) {
                $out[] = $row;

                continue;
            }

            $key = $row['verb'].':'.$row['target']->getMorphClass().':'.$row['target']->getKey()
                .':'.$row['at']->toDateString();

            if (! isset($out[$key])) {
                $out[$key] = [...$row, 'people' => []];
            }

            $out[$key]['people'][] = $row['who'];
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function item(array $row, ?Carbon $seenAt): ResponseItem
    {
        return new ResponseItem(
            id: $row['id'],
            kind: $row['kind'],
            icon: $row['icon'],
            sentence: $this->sentence($row),
            entryTitle: InteractionTarget::titleFor($row['target']),
            entryHref: $row['target']?->url() ?? '/',
            body: $row['body'],
            age: $row['at']->diffForHumans(),
            isNew: $seenAt === null || $row['at']->greaterThan($seenAt),
        );
    }

    /**
     * Said out loud: "Clare A., Justin M. and Brian D. gave kudos on".
     *
     * @param  array<string, mixed>  $row
     */
    private function sentence(array $row): string
    {
        $people = array_values(array_unique(array_filter($row['people'] ?? [$row['who']])));

        if ($people === []) {
            $count = count($row['people'] ?? []);

            return ($count > 1 ? "{$count} people " : 'Someone ').$row['verb'];
        }

        $named = array_slice($people, 0, 3);
        $rest = count($people) - count($named);
        $who = Arr::join($named, ', ', ' and ');

        if ($rest > 0) {
            $who .= " and {$rest} others";
        }

        return $who.' '.$row['verb'];
    }
}
