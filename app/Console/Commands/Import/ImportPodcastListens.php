<?php

namespace App\Console\Commands\Import;

use App\Services\PocketCasts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('podcast:listens {file : Path to the Pocket Casts data.txt export} {--csv= : Output CSV path (defaults to data/listens.csv)}')]
#[Description('Build a podcast listen-history CSV from a Pocket Casts data export, enriching played/in-progress episodes with titles, shows and dates via the Pocket Casts API')]
class ImportPodcastListens extends Command
{
    /** @var list<string> */
    private const COLUMNS = ['occurred_at', 'status', 'title', 'show', 'author', 'published', 'duration', 'played_up_to', 'starred', 'url', 'podcast_uuid', 'episode_uuid'];

    /** Pocket Casts playing-status codes that count as "listened", mapped to a label. */
    private const LISTENED = ['2' => 'in-progress', '3' => 'played'];

    public function handle(PocketCasts $pocketCasts): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("Export not found: {$file}");

            return self::FAILURE;
        }

        $episodes = $this->parseExport((string) file_get_contents($file));

        if ($episodes === []) {
            $this->components->warn('No played or in-progress episodes found in the export.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Found %d listened episode(s) in the export. Resolving metadata via Pocket Casts...', count($episodes)));

        $meta = $this->buildMetadataMap($pocketCasts);

        $rows = [];
        $resolved = 0;

        foreach ($episodes as $uuid => $episode) {
            $info = $meta[$uuid] ?? null;
            $resolved += $info !== null ? 1 : 0;

            $rows[] = [
                'occurred_at' => $this->localTime($episode['modified']),
                'status' => $episode['status'],
                'title' => $info['title'] ?? '',
                'show' => $info['show'] ?? '',
                'author' => $info['author'] ?? '',
                'published' => $this->localTime($info['published'] ?? ''),
                'duration' => $episode['duration'] !== '' ? $episode['duration'] : (string) ($info['duration'] ?? ''),
                'played_up_to' => $episode['played_up_to'],
                'starred' => $episode['starred'] ? '1' : '',
                'url' => $info['url'] ?? '',
                'podcast_uuid' => $info['podcast_uuid'] ?? '',
                'episode_uuid' => $uuid,
            ];
        }

        usort($rows, fn (array $first, array $second): int => strcmp($first['occurred_at'], $second['occurred_at']));

        $path = $this->csvPath();
        $this->writeCsv($path, $rows);

        $this->components->info(sprintf('Wrote %d listen(s) to %s (%d enriched, %d without metadata).', count($rows), $path, $resolved, count($rows) - $resolved));

        return self::SUCCESS;
    }

    /**
     * Parse the Episodes section of a Pocket Casts export, keeping only the
     * episodes that were played or left in progress.
     *
     * @return array<string, array{status: string, played_up_to: string, duration: string, starred: bool, modified: string}>
     */
    private function parseExport(string $contents): array
    {
        $episodes = [];
        $inSection = false;
        $headerSeen = false;

        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
            if ($line === 'Episodes') {
                $inSection = true;

                continue;
            }

            if (! $inSection || $line === '--------') {
                continue;
            }

            if ($line === '') {
                break; // a blank line closes the section
            }

            if (! $headerSeen) {
                $headerSeen = true; // skip the column header row

                continue;
            }

            $cells = str_getcsv($line, ',', '"', '\\');

            if (count($cells) < 7 || ! isset(self::LISTENED[$cells[1]])) {
                continue;
            }

            $episodes[$cells[0]] = [
                'status' => self::LISTENED[$cells[1]],
                'played_up_to' => $cells[2],
                'duration' => $cells[4],
                'starred' => strtolower($cells[5]) === 'true',
                'modified' => $cells[6],
            ];
        }

        return $episodes;
    }

    /**
     * Build an episode-uuid => metadata map. Recent history, in-progress and
     * starred lists carry full per-episode metadata; subscribed shows' episode
     * lists fill in older listens. Richer (history) entries win.
     *
     * @return array<string, array{title: string, show: string, author: string, published: string, duration: int, url: string, podcast_uuid: string}>
     */
    private function buildMetadataMap(PocketCasts $pocketCasts): array
    {
        $map = [];

        foreach (['history', 'inProgress', 'starred'] as $method) {
            foreach ($pocketCasts->{$method}()['episodes'] ?? [] as $episode) {
                if (! isset($episode['uuid'])) {
                    continue;
                }

                $map[$episode['uuid']] = [
                    'title' => (string) ($episode['title'] ?? ''),
                    'show' => (string) ($episode['podcastTitle'] ?? ''),
                    'author' => (string) ($episode['author'] ?? ''),
                    'published' => (string) ($episode['published'] ?? ''),
                    'duration' => (int) ($episode['duration'] ?? 0),
                    'url' => (string) ($episode['url'] ?? ''),
                    'podcast_uuid' => (string) ($episode['podcastUuid'] ?? ''),
                ];
            }
        }

        foreach ($pocketCasts->subscriptions()['podcasts'] ?? [] as $subscription) {
            $uuid = $subscription['uuid'] ?? null;

            if (! is_string($uuid)) {
                continue;
            }

            $detail = $pocketCasts->podcast($uuid)['podcast'] ?? [];
            $show = (string) ($detail['title'] ?? $subscription['title'] ?? '');
            $author = (string) ($detail['author'] ?? $subscription['author'] ?? '');

            foreach ($detail['episodes'] ?? [] as $episode) {
                if (! isset($episode['uuid']) || isset($map[$episode['uuid']])) {
                    continue;
                }

                $map[$episode['uuid']] = [
                    'title' => (string) ($episode['title'] ?? ''),
                    'show' => $show,
                    'author' => $author,
                    'published' => (string) ($episode['published'] ?? ''),
                    'duration' => (int) ($episode['duration'] ?? 0),
                    'url' => (string) ($episode['url'] ?? ''),
                    'podcast_uuid' => $uuid,
                ];
            }
        }

        return $map;
    }

    /**
     * Convert a Pocket Casts UTC timestamp to the home timezone's wall-clock,
     * matching how the other data/*.csv seeds store times.
     */
    private function localTime(string $iso): string
    {
        if ($iso === '') {
            return '';
        }

        return Carbon::parse($iso)->setTimezone((string) config('app.home_timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, self::COLUMNS, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($row[$column] ?? ''), self::COLUMNS), ',', '"', '\\');
        }

        fclose($handle);
    }

    private function csvPath(): string
    {
        return $this->option('csv') ?: base_path('data/listens.csv');
    }
}
