<?php

namespace App\Data;

use App\Models\Subject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * A subject as its page renders it. `kind` and `category` carry their display
 * labels here, not their storage values: nothing on the page filters by them.
 */
final readonly class SubjectData implements Arrayable, JsonSerializable
{
    /**
     * @param  array{src: string, srcset: ?string, full: string}|null  $cover
     * @param  array<int, mixed>|null  $bio
     * @param  list<array{label: string, value: string}>  $facts
     * @param  list<array{platform: string, value: string}>  $identities
     * @param  list<array{platform: string, url: string}>  $identityLinks
     */
    private function __construct(
        public int $id,
        public string $kind,
        public ?string $category,
        public string $name,
        public string $slug,
        public string $url,
        public ?array $cover,
        public ?array $bio,
        public array $facts,
        public array $identities,
        public array $identityLinks,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    public static function from(Subject $subject): self
    {
        return new self(
            id: $subject->id,
            kind: $subject->kind->label(),
            category: $subject->category?->label(),
            name: $subject->name,
            slug: $subject->slug,
            url: $subject->url(),
            cover: $subject->coverPhoto(),
            bio: $subject->bio,
            facts: $subject->meta->toArray(),
            identities: $subject->identities->toArray(),
            identityLinks: self::identityLinks($subject->identities),
            latitude: $subject->latitude,
            longitude: $subject->longitude,
        );
    }

    /**
     * Identity entries worth a `rel="me"` link, resolved per platform through
     * {@see SubjectIdentities::urlFor()} and dropped when not a URL.
     *
     * @return list<array{platform: string, url: string}>
     */
    private static function identityLinks(SubjectIdentities $identities): array
    {
        return Collection::make($identities->toArray())
            ->pluck('platform')
            ->unique()
            ->map(fn (string $platform): array => ['platform' => $platform, 'url' => $identities->urlFor($platform)])
            ->filter(fn (array $link): bool => str_starts_with($link['url'], 'http'))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'category' => $this->category,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->url,
            'cover' => $this->cover,
            'bio' => $this->bio,
            'facts' => $this->facts,
            'identities' => $this->identities,
            'identityLinks' => $this->identityLinks,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
