<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The context card above a response: what this post is answering, and how.
 *
 * The IndieWeb calls this reply context. It stands in for the target the way a
 * quoted tweet does, so somebody reading the reply knows what it replies to
 * without following the link first.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ResponseData implements Arrayable, JsonSerializable
{
    public function __construct(
        /** The ResponseKind value, which drives the icon. */
        public string $kind,
        /** What I did, as a sentence: "Replied to". */
        public string $label,
        /** The microformats2 property the link carries. */
        public string $property,
        public string $url,
        /** What to call the target: its own title, or where it lives. */
        public string $title,
        /**
         * The RSVP answer, on an RSVP and nothing else. Its wording is already
         * in the label; this is the machine-readable half.
         */
        public ?string $rsvp,
        /** The site responded to, for a target that is not one of mine. */
        public ?string $host,
        public ?string $favicon,
        /**
         * The target's own card, for one of mine. Null for anybody else's,
         * where the host and the URL are all we honestly know about it.
         *
         * @var array<string, mixed>|null
         */
        public ?array $preview,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'property' => $this->property,
            'url' => $this->url,
            'title' => $this->title,
            'rsvp' => $this->rsvp,
            'host' => $this->host,
            'favicon' => $this->favicon,
            'preview' => $this->preview,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
