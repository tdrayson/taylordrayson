<?php

namespace App\Data;

use App\Enums\WebmentionKind;
use App\Models\Webmention;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One face in the pile: somebody who reacted from their own site.
 *
 * A like and a reacji are the same gesture wearing a different emoji, so both
 * land here rather than one being a count and the other a comment. The emoji
 * rides along so a 🚀 stays a 🚀 instead of being rounded to the nearest
 * offered reaction.
 */
final readonly class FaceData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $name,
        public ?string $url,
        public ?string $photo,
        public string $emoji,
    ) {}

    public static function fromWebmention(Webmention $mention): self
    {
        return new self(
            name: $mention->author_name ?: (string) (parse_url($mention->source_url, PHP_URL_HOST) ?: $mention->source_url),
            url: $mention->author_url ?: $mention->source_url,
            photo: $mention->author_photo_path === null ? null : '/'.ltrim($mention->author_photo_path, '/'),
            emoji: $mention->kind === WebmentionKind::Reacji->value && filled($mention->content)
                ? trim((string) $mention->content)
                : '❤️',
        );
    }

    /**
     * @return array{name: string, url: ?string, photo: ?string, emoji: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'photo' => $this->photo,
            'emoji' => $this->emoji,
        ];
    }

    /**
     * @return array{name: string, url: ?string, photo: ?string, emoji: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
