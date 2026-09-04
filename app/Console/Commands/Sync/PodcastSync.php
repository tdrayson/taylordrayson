<?php

namespace App\Console\Commands\Sync;

use App\Jobs\StorePodcastMedia;
use App\Models\Podcast;
use App\Queries\PodcastEpisodeCount;
use App\Services\ThisWeekWith;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('podcast:sync {--per-page=50 : Episodes to request per page} {--full : Re-fetch and re-map every episode}')]
#[Description('Sync This Week With episodes from the website API')]
class PodcastSync extends Command
{
    /**
     * How many already-stored episodes in a row end an incremental run. More than
     * one so late-added show notes get re-mapped and a half-finished run's hole
     * is filled rather than stranded.
     */
    private const CONSECUTIVE_KNOWN_LIMIT = 5;

    /**
     * Pull new episodes, stopping once the feed reaches ones already stored.
     * `--full` walks the whole feed instead, for a backfill or a re-map after
     * changing `mapEpisode()`.
     */
    public function handle(ThisWeekWith $thisWeekWith): int
    {
        $perPage = (int) $this->option('per-page');
        $full = (bool) $this->option('full');

        $created = 0;
        $seen = 0;
        $consecutiveKnown = 0;
        $changed = false;

        try {
            foreach ($thisWeekWith->episodes($perPage) as $episode) {
                $podcast = $this->store($this->mapEpisode($episode));
                $changed = $changed || $podcast->wasRecentlyCreated || $podcast->wasChanged();

                if ($podcast->wasRecentlyCreated) {
                    $created++;
                    $consecutiveKnown = 0;

                    // Mirror it now rather than waiting for the next back-fill,
                    // so a new episode stops depending on the publisher as soon
                    // as it appears. Only for new episodes: a --full re-map
                    // walks all 255 and would re-queue the whole archive.
                    StorePodcastMedia::dispatch($podcast);

                    continue;
                }

                $seen++;
                $consecutiveKnown++;

                if (! $full && $consecutiveKnown >= self::CONSECUTIVE_KNOWN_LIMIT) {
                    break;
                }
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($created === 0 && $seen === 0) {
            $this->warn('No episodes returned from the API.');

            return self::FAILURE;
        }

        // Only when something actually moved: at this cadence most runs change
        // nothing, and the timeline's episode figure is cached until midnight.
        if ($changed) {
            PodcastEpisodeCount::forget();
        }

        $this->info("Synced {$created} new episode(s), {$seen} already stored.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $episode
     * @return array<string, mixed>
     */
    private function mapEpisode(array $episode): array
    {
        return [
            'occurred_at' => Carbon::parse($episode['date'])->format('Y-m-d H:i:s'),
            'season_number' => $episode['season']['number'] ?? null,
            'episode_number' => $episode['episode_number'] ?? null,
            'topic' => trim(rtrim((string) ($episode['main_topics'] ?? ''), ' -')) ?: null,
            'show_notes' => $this->cleanShowNotes((string) ($episode['content'] ?? '')),
            'transcript' => trim((string) ($episode['transcript'] ?? '')) ?: null,
            'duration' => ($episode['duration'] ?? 0) ?: null,
            'audio_url' => $episode['audio_url'] ?? null,
            'video_url' => $episode['video_link'] ?? null,
            'thumbnail' => $episode['thumbnail'] ?? null,
            'cover_image' => $episode['cover_image'] ?? null,
        ];
    }

    /**
     * Preserve line breaks and links, but allowlist the HTML down to safe anchors
     * (href only, safe schemes) so the stored notes are safe to render with v-html.
     */
    private function cleanShowNotes(string $content): ?string
    {
        $content = str_replace("\r\n", "\n", $content);
        $content = HtmlSanitizer::clean($content, [
            'a' => ['href'],
            'p' => [],
            'br' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'blockquote' => [],
            'h2' => [],
            'h3' => [],
        ]);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content) ?: null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function store(array $row): Podcast
    {
        return Podcast::updateOrCreate(
            ['season_number' => $row['season_number'], 'episode_number' => $row['episode_number']],
            $row,
        );
    }
}
