<?php

namespace App\Console\Commands;

use App\Models\Appearance;
use App\Support\YouTube;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('appearances:thumbnails {--force : Re-download even when a cover already exists}')]
#[Description('Download YouTube thumbnails for appearances and store them as cover assets')]
class DownloadAppearanceThumbnails extends Command
{
    /**
     * YouTube thumbnail qualities, largest first. When a size is unavailable
     * YouTube serves a 120x90 grey placeholder, so anything but the smallest
     * tier must decode wider than that to count as a real image.
     *
     * @var array<int, string>
     */
    private const QUALITIES = ['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault', 'default'];

    private const PLACEHOLDER_WIDTH = 120;

    /**
     * Download a cover thumbnail for every appearance that has a YouTube video
     * but no stored cover asset (unless --force is given).
     */
    public function handle(): int
    {
        $appearances = Appearance::query()->with('media')->get();
        $force = (bool) $this->option('force');
        $stored = 0;

        foreach ($appearances as $appearance) {
            if ($appearance->getFirstMedia('cover') && ! $force) {
                continue;
            }

            $videoId = YouTube::id($appearance->video_url);

            if (! $videoId) {
                continue;
            }

            if ($this->storeThumbnail($appearance, $videoId)) {
                $stored++;
                $this->line("Stored thumbnail for #{$appearance->id} ({$videoId})");
            } else {
                $this->warn("No thumbnail found for #{$appearance->id} ({$videoId})");
            }
        }

        $this->info("Done. Stored {$stored} thumbnail(s).");

        return self::SUCCESS;
    }

    /**
     * Fetch the largest available YouTube thumbnail and store it as the
     * appearance's cover media, replacing any existing cover.
     */
    private function storeThumbnail(Appearance $appearance, string $videoId): bool
    {
        $thumbnail = $this->fetchLargestThumbnail($videoId);

        if (! $thumbnail) {
            return false;
        }

        $appearance->clearMediaCollection('cover');
        $appearance->addMediaFromString($thumbnail['body'])
            ->usingFileName("{$videoId}.jpg")
            ->toMediaCollection('cover');

        return true;
    }

    /**
     * Walk the quality ladder from largest to smallest and return the first tier
     * that resolves to a genuine image, skipping the grey placeholder YouTube
     * serves for missing sizes.
     *
     * @return array{body: string, width: int, height: int}|null
     */
    private function fetchLargestThumbnail(string $videoId): ?array
    {
        foreach (self::QUALITIES as $quality) {
            $response = Http::get("https://i.ytimg.com/vi/{$videoId}/{$quality}.jpg");

            if (! $response->successful() || $response->body() === '') {
                continue;
            }

            $dimensions = @getimagesizefromstring($response->body());

            if ($dimensions === false) {
                continue;
            }

            [$width, $height] = $dimensions;

            if ($quality !== 'default' && $width <= self::PLACEHOLDER_WIDTH) {
                continue;
            }

            return ['body' => $response->body(), 'width' => $width, 'height' => $height];
        }

        return null;
    }
}
