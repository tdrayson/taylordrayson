<?php

namespace App\Queries\Hub;

/**
 * Everything a person has said, newest first, across comments, webmentions,
 * reactions and syndicated responses.
 *
 * SCAFFOLDING: a fixed pool until the four tables behind it are read. The shape
 * it returns is the real one, so only the body of pool() changes.
 */
final class RecentResponses
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(int $limit): array
    {
        return array_slice(self::pool(), 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pool(): array
    {
        $entries = [
            ['Evening ride', '/activities'],
            ['Lunchtime walk', '/activities'],
            ['Friday run', '/activities'],
            ['Notes on webmentions', '/articles/rebuilding-the-timeline'],
            ['Rebuilding the timeline', '/articles/rebuilding-the-timeline'],
            ['Sunday long one', '/activities'],
        ];

        $people = ['Clare A.', 'Justin M.', 'Brian D.', 'Sam P.', 'Priya R.'];

        $rows = [
            self::row('r1', 'like', 'ThumbsUpIcon', 'Clare A. gave kudos on', $entries[0], null, '18 September', true),
            self::row('r2', 'like', 'ThumbsUpIcon', 'Justin M. gave kudos on', $entries[1], null, '13 September', true),
            self::row('r3', 'reply', 'MailReply01Icon', 'Clare A. replied on', $entries[2], 'That hill again! Well done you.', '12 September', false),
            self::row('r4', 'like', 'ThumbsUpIcon', 'Clare A., Justin M. and Brian D. gave kudos on', $entries[2], null, '12 September', false),
            self::row('r5', 'mention', 'Link04Icon', 'adactio.com linked to', $entries[3], null, '11 September', false),
            self::row('r6', 'comment', 'Comment01Icon', 'Sam P. commented on', $entries[4], 'This is the writeup I keep sending people. Any plans to open source the timeline bit?', '9 September', false),
            self::row('r7', 'like', 'HeartIcon', 'Priya R. reacted to', $entries[4], null, '8 September', false),
            self::row('r8', 'like', 'ThumbsUpIcon', 'Clare A. gave kudos on', $entries[5], null, '7 September', false),
            self::row('r9', 'reply', 'MailReply01Icon', 'Brian D. replied on', $entries[5], 'Pace looking good in that last mile.', '7 September', false),
            self::row('r10', 'mention', 'Link04Icon', 'maxbock.de linked to', $entries[3], null, '5 September', false),
        ];

        // Older rows, so the cap on the hub is visible.
        for ($i = 11; $i <= 26; $i++) {
            $entry = $entries[$i % count($entries)];
            $who = $people[$i % count($people)];

            $rows[] = self::row("r{$i}", 'like', 'ThumbsUpIcon', "{$who} gave kudos on", $entry, null, (30 - $i).' August', false);
        }

        return $rows;
    }

    /**
     * @param  array{0: string, 1: string}  $entry
     * @return array<string, mixed>
     */
    private static function row(string $id, string $kind, string $icon, string $sentence, array $entry, ?string $body, string $age, bool $isNew): array
    {
        return [
            'id' => $id,
            'kind' => $kind,
            'icon' => $icon,
            'sentence' => $sentence,
            'entry' => ['title' => $entry[0], 'href' => $entry[1]],
            'body' => $body,
            'age' => $age,
            'isNew' => $isNew,
        ];
    }
}
