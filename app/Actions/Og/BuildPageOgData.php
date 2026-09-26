<?php

namespace App\Actions\Og;

use Illuminate\Support\Str;

/**
 * Build the Open Graph card payload for a page: its title, eyebrow, accent and
 * description drawn on the type-less text (or home) layout.
 */
final class BuildPageOgData
{
    public function __construct(
        private readonly OgGalleryUrls $galleryUrls,
    ) {}

    /**
     * The card view data, less the cutout, with each raw value trimmed and capped as the card draws it.
     *
     * @param  string|null  $variant  "home" for the home layout, anything else for text.
     * @return array{layout: string, accent: string, eyebrow: ?string, title: string, date: null, subtitle: ?string, image: null}
     */
    public function __invoke(?string $title, ?string $eyebrow, ?string $accent, ?string $variant, ?string $description): array
    {
        return [
            'layout' => $variant === 'home' ? 'home' : 'text',
            'accent' => $this->galleryUrls->accent($accent),
            'eyebrow' => Str::limit(trim((string) $eyebrow), 60, '') ?: null,
            'title' => Str::limit(trim((string) $title) ?: config('identity.name'), 160, ''),
            'date' => null,
            'subtitle' => Str::limit(trim((string) $description), 200, '') ?: null,
            'image' => null,
        ];
    }
}
