<?php

namespace App\Actions\Appearances;

use App\Models\Appearance;
use App\Support\EntryInstant;

class CreateAppearance
{
    /**
     * @param  array{title: string, show_name?: string|null, type?: string, occurred_at?: string|null, url?: string|null, video_url?: string|null, audio_url?: string|null, description?: string|null, duration?: int|null}  $attributes
     */
    public function __invoke(array $attributes): Appearance
    {
        return Appearance::create([
            ...$attributes,
            'type' => $attributes['type'] ?? 'podcast',
            'occurred_at' => $attributes['occurred_at'] ?? EntryInstant::nowLocal(),
        ]);
    }
}
