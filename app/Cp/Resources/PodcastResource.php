<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Podcast;

class PodcastResource extends TimelineCpResource
{
    public function model(): string
    {
        return Podcast::class;
    }

    public function slug(): string
    {
        return 'this-week-with';
    }

    public function label(): string
    {
        return 'Episode';
    }

    public function pluralLabel(): string
    {
        return 'This Week With';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['topic'];
    }

    /** @return array<int, array{area: string, tab?: string, title?: string, fields: array<int, string>}> */
    public function sections(): array
    {
        return [
            ['area' => 'main', 'fields' => ['topic', 'show_notes', 'transcript']],
            ['area' => 'sidebar', 'title' => 'Episode', 'fields' => ['occurred_at', 'season_number', 'episode_number', 'duration', 'audio_url', 'video_url']],
            ['area' => 'sidebar', 'title' => 'Media', 'fields' => ['thumbnail', 'cover_image']],
        ];
    }
}
