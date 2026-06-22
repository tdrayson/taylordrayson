<?php

namespace App\Console\Commands;

use App\Models\Podcast;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('podcast:sync {--per-page=50 : Episodes to request per page}')]
#[Description('Sync This Week With episodes from the website API into data/podcasts.csv and the database')]
class PodcastSync extends Command
{
    private const ENDPOINT = 'https://www.thisweekwith.co.uk/wp-json/podcast/v1/episodes';

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
        'youtube_url',
        'thumbnail',
        'cover_image',
    ];

    public function handle(): int
    {
        $perPage = (int) $this->option('per-page');
        $rows = $this->fetchEpisodes($perPage);

        if ($rows === null) {
            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('No episodes returned from the API.');

            return self::FAILURE;
        }

        $this->writeCsv($rows);
        $this->syncDatabase($rows);

        $this->info('Synced '.count($rows).' episodes to data/podcasts.csv and the database.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchEpisodes(int $perPage): ?array
    {
        $rows = [];
        $page = 1;

        do {
            $response = Http::acceptJson()->get(self::ENDPOINT, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                $this->error("API request failed on page {$page}: {$response->status()}");

                return null;
            }

            foreach ($response->json('episodes', []) as $episode) {
                $rows[] = $this->mapEpisode($episode);
            }

            $totalPages = (int) $response->json('total_pages', 1);
            $this->info("Fetched page {$page} of {$totalPages}.");
            $page++;
        } while ($page <= $totalPages);

        return $rows;
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
            'youtube_url' => $episode['video_link'] ?? null,
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
        $content = HtmlSanitizer::clean($content, ['a' => ['href']]);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content) ?: null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCsv(array $rows): void
    {
        $handle = fopen(base_path('data/podcasts.csv'), 'w');
        fputcsv($handle, self::HEADERS);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): string => (string) ($row[$header] ?? ''), self::HEADERS));
        }

        fclose($handle);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncDatabase(array $rows): void
    {
        foreach ($rows as $row) {
            Podcast::updateOrCreate(
                ['season_number' => $row['season_number'], 'episode_number' => $row['episode_number']],
                $row,
            );
        }
    }
}
