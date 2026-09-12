<?php

namespace App\Actions\Syndicated;

use App\Data\SyndicatedResponseData;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Services\Strava\Client;
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

    public function __invoke(Activity $activity): void
    {
        if ($activity->source !== Source::Strava->value || blank($activity->source_id)) {
            return;
        }

        $kudos = $this->strava->kudos($activity->source_id);
        $comments = $this->strava->comments($activity->source_id);

        // Null is a failed request, not an empty list. Treating it as empty
        // would wipe the responses we already hold.
        if ($kudos === null || $comments === null) {
            return;
        }

        $url = self::url($activity->source_id);

        ($this->reconcile)($activity, Source::Strava, [
            ...array_map(fn (array $athlete): SyndicatedResponseData => new SyndicatedResponseData(
                kind: WebmentionKind::Like,
                authorName: self::name($athlete),
                occurredAt: $activity->occurred_at,
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
        ]);
    }

    /** Strava gives a first name and an initial, and nothing else. */
    private static function name(array $athlete): string
    {
        return trim(($athlete['firstname'] ?? '').' '.($athlete['lastname'] ?? '')) ?: 'Someone on Strava';
    }

    public static function url(string $sourceId): string
    {
        return "https://www.strava.com/activities/{$sourceId}";
    }
}
