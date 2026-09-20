<?php

namespace App\Actions\Syndicated;

use App\Data\SyndicatedResponseData;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Services\Strava\Client;
use App\Support\EntryInstant;
use App\Support\PortableText;
use Carbon\Carbon;

/**
 * Everything Strava holds for one activity, stored against it.
 *
 * Kudos are likes under a different name, so they are normalised on the way in
 * and Strava's word for them survives only in the byline.
 */
final class PullStravaResponses
{
    public function __construct(
        private readonly Client $strava,
        private readonly ReconcileResponses $reconcile,
    ) {}

    /**
     * @param  bool  $withComments  False to leave the comments endpoint alone, when the caller already knows from
     *                              the summary that there are none. Replies are then reconciled as unvouched.
     * @return bool Whether Strava answered and the responses were reconciled. False is a request that failed,
     *              which a caller counting its work must not record as a pull.
     */
    public function __invoke(Activity $activity, bool $withComments = true): bool
    {
        if ($activity->source !== Source::Strava->value || blank($activity->source_id)) {
            return false;
        }

        $kudos = $this->strava->kudos($activity->source_id);
        $comments = $withComments ? $this->strava->comments($activity->source_id) : [];

        // Null is a failed request, not an empty list. Treating it as empty
        // would wipe the responses we already hold.
        if ($kudos === null || $comments === null) {
            return false;
        }

        $url = $activity->platform_url;

        // A page returned at its ceiling is as much as Strava will say in one
        // request, not necessarily all there is. There is no paging loop here
        // on purpose: the command budgets its requests per activity to stay
        // inside the rate limit, and an unbounded walk would break that
        // accounting. So a full page buys safety instead, holding back the
        // deletions rather than reading a truncated list as the whole truth.
        //
        // An unfetched comments endpoint is the same problem in its strongest
        // form: an empty list we never asked for proves nothing at all.
        $unvouched = [
            ...(count($kudos) >= Client::RESPONSES_PER_PAGE ? [WebmentionKind::Like] : []),
            ...(! $withComments || count($comments) >= Client::RESPONSES_PER_PAGE ? [WebmentionKind::Reply] : []),
        ];

        // occurred_at is a wall-clock reading, not an instant: a kudo has no
        // timestamp of its own, so it borrows the activity's, converted via
        // the activity's own timezone.
        $kudoOccurredAt = EntryInstant::utc($activity->occurred_at, $activity->timezone()) ?? $activity->occurred_at;

        ($this->reconcile)($activity, Source::Strava, [
            ...array_map(fn (array $athlete): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Like,
                authorName: self::name($athlete),
                occurredAt: $kudoOccurredAt,
                url: $url,
            ), $kudos),

            ...array_map(fn (array $comment): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Reply,
                authorName: self::name($comment['athlete'] ?? []),
                occurredAt: Carbon::parse($comment['created_at']),
                sourceId: (string) $comment['id'],
                body: PortableText::fromPlainText((string) ($comment['text'] ?? '')),
                url: $url,
            ), $comments),
        ], $unvouched);

        return true;
    }

    /** Strava gives a first name and an initial, and nothing else. */
    private static function name(array $athlete): string
    {
        return trim(($athlete['firstname'] ?? '').' '.($athlete['lastname'] ?? '')) ?: 'Someone on Strava';
    }
}
