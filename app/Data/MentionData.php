<?php

namespace App\Data;

use App\Enums\WebmentionKind;
use Carbon\CarbonInterface;

/**
 * What a source page turned out to be saying about one of our URLs, after its
 * microformats have been read.
 */
final readonly class MentionData
{
    public function __construct(
        public WebmentionKind $kind,
        public ?string $authorName,
        public ?string $authorUrl,
        public ?string $authorPhoto,
        public ?string $content,
        public ?CarbonInterface $publishedAt,
        /**
         * Set when the reply is a reacji: an in-reply-to whose whole content is
         * one emoji. IndieWeb has no property for it, so it is detected here.
         */
        public ?string $emoji = null,
    ) {}

    /** A bare link with nothing readable behind it, which is still worth showing. */
    public static function bare(): self
    {
        return new self(WebmentionKind::Mention, null, null, null, null, null);
    }

    public function isReacji(): bool
    {
        return $this->emoji !== null;
    }
}
