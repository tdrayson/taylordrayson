<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Media;

class MediaResource extends TimelineCpResource
{
    public function model(): string
    {
        return Media::class;
    }

    public function slug(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Media';
    }

    public function pluralLabel(): string
    {
        return 'Media';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['title', 'type'];
    }

    /** @return array<string, array<string, mixed>> */
    public function fieldOverrides(): array
    {
        return [
            'type' => ['type' => 'select', 'options' => [
                ['value' => 'film', 'label' => 'Film'],
                ['value' => 'tv', 'label' => 'TV'],
                ['value' => 'tv_episode', 'label' => 'TV episode'],
                ['value' => 'book', 'label' => 'Book'],
            ]],
        ];
    }
}
