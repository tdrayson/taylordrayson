<?php

namespace App\Console\Commands\Sync;

use App\Jobs\StorePodcastMedia;
use App\Models\Podcast;
use App\Services\ThisWeekWith;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('podcast:sync {--per-page=50 : Episodes to request per page} {--full : Re-fetch and re-map every episode} {--csv= : Target CSV path; defaults to data/podcasts.csv}')]
#[Description('Sync This Week With episodes from the website API into data/podcasts.csv and the database')]
class PodcastSync extends Command
{
    /**
     * How many already-stored episodes in a row end an incremental run.
     *
     * The endpoint returns newest first, so one known episode usually means
     * everything older is known too. Requiring a run of them costs a few
     * upserts and buys two things: the newest episodes are re-mapped every
     * run, so show notes or a transcript added after publication are picked
     * up, and a hole left by a half-finished run is filled rather than being
     * stranded behind a stop-at-the-first-known rule.
     */
    private const CONSECUTIVE_KNOWN_LIMIT = 5;

    /** @var list<string> */
    private const HEADERS = [
        'occurred_at',
        'season_number',
        'episode_number',
        'topic',
        'show_notes',
        'transcript',
        'duration',
        'audio_url',
        'video_url',
        'thumbnail',
        'cover_image',
    ];

    /**
     * Pull new episodes, stopping as soon as the feed reaches episodes already
     * stored. Every run used to walk all six pages and re-upsert all 254
     * episodes to find the nought or one that were new.
     *
     * `--full` restores that whole-feed pass, which is what a backfill or a
     * re-map after changing `mapEpisode()` wants.
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

        // Only when something actually moved: the mirror is a full rewrite of
        // every episode, and at this cadence most runs change nothing.
        //
        // Rebuilt from the database rather than from the episodes fetched this
        // run, because an incremental run holds only a handful and writing
        // those would truncate the mirror to the newest few.
        if ($changed) {
            $this->writeCsv();
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
     * Mirror every stored episode to the CSV, newest first to match how the
     * file was written when it was built straight from the API response.
     */
    private function writeCsv(): void
    {
        $handle = fopen($this->option('csv') ?: base_path('data/podcasts.csv'), 'w');
        fputcsv($handle, self::HEADERS);

        Podcast::query()
            ->orderByDesc('occurred_at')
            ->each(function (Podcast $podcast) use ($handle): void {
                fputcsv($handle, array_map(
                    fn (string $header): string => $header === 'occurred_at'
                        ? $podcast->occurred_at->format('Y-m-d H:i:s')
                        : (string) ($podcast->getAttribute($header) ?? ''),
                    self::HEADERS,
                ));
            });

        fclose($handle);
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
