<?php

namespace App\Data\Aspects;

/**
 * The images an entry page marks up: its u-featured image and u-photo
 * photos, as absolute URLs with the alt text the page gives them.
 */
final readonly class Imagery
{
    /**
     * @param  list<string>  $photos
     */
    private function __construct(
        public ?string $featured,
        public string $featuredAlt,
        public array $photos,
    ) {}

    /**
     * Null when there is nothing to publish.
     *
     * @param  ?string  $featured  The featured image URL, absolute or root-relative.
     * @param  array<int, ?string>  $photos  Photo URLs in display order; nulls are skipped.
     * @param  string  $featuredAlt  The alt text the page renders on the featured image.
     */
    public static function of(?string $featured, array $photos = [], string $featuredAlt = ''): ?self
    {
        $photos = array_values(array_map(url(...), array_filter($photos)));

        if (($featured === null || $featured === '') && $photos === []) {
            return null;
        }

        return new self($featured ? url($featured) : null, $featuredAlt, $photos);
    }

    /**
     * A gallery entry's photos, as the page renders them: the card conversion.
     *
     * @param  array<int, array{src: string}>  $gallery  From HasAttachments::galleryPhotos().
     */
    public static function gallery(array $gallery): ?self
    {
        return self::of(null, array_column($gallery, 'src'));
    }
}
