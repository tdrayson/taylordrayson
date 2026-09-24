<?php

namespace App\Data;

use App\Enums\WebmentionKind;
use Carbon\CarbonInterface;

/**
 * One response as a source just reported it, before it is stored.
 *
 * @param  array<int, mixed>|null  $body  Portable Text, on the kinds that carry prose.
 */
final readonly class SyndicatedResponseData
{
    public function __construct(
        public WebmentionKind $kind,
        public string $authorName,
        public CarbonInterface $occurredAt,
        /** The source's own id, on the kinds that have one. */
        public ?string $sourceId = null,
        public ?string $parentSourceId = null,
        public ?string $emoji = null,
        public ?array $body = null,
        public ?string $url = null,
        /** Fetched and stored locally, so no reader requests a third party. */
        public ?string $authorPhotoUrl = null,
        /** The source says I wrote it. */
        public bool $mine = false,
    ) {}

    /** The same response, known to be mine. */
    public function asMine(): self
    {
        return new self(
            kind: $this->kind,
            authorName: $this->authorName,
            occurredAt: $this->occurredAt,
            sourceId: $this->sourceId,
            parentSourceId: $this->parentSourceId,
            emoji: $this->emoji,
            body: $this->body,
            url: $this->url,
            authorPhotoUrl: $this->authorPhotoUrl,
            mine: true,
        );
    }

    /**
     * The columns this becomes, minus the ones only the writer knows.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'kind' => $this->kind,
            'author_name' => $this->authorName,
            'occurred_at' => $this->occurredAt,
            'source_id' => $this->sourceId,
            'parent_source_id' => $this->parentSourceId,
            'emoji' => $this->emoji,
            'body' => $this->body,
            'url' => $this->url,
            'author_photo_url' => $this->authorPhotoUrl,
            // Only ever set, never cleared: a reply marked mine by hand has to
            // survive a sync whose payload cannot tell.
            ...($this->mine ? ['mine' => true] : []),
        ];
    }
}
