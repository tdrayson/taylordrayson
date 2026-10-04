<?php

namespace App\Data\LinkPage;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Everything a link-in-bio card renders. Contact hrefs are null when the detail is not configured.
 */
final readonly class LinkPageData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<LinkSection>  $sections
     * @param  list<LinkItem>  $socials
     * @param  list<int>  $heatmap  Activity levels 0-3, oldest day first.
     */
    public function __construct(
        public string $page,
        public string $name,
        public ?string $organisation,
        public string $bio,
        public string $avatar,
        public string $contactHref,
        public ?string $detailsHref,
        public ?string $phoneHref,
        public ?string $emailHref,
        public ?string $whatsappHref,
        public array $sections,
        public string $socialHeading,
        public array $socials,
        public array $heatmap,
        public int $coffees,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'name' => $this->name,
            'organisation' => $this->organisation,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'contactHref' => $this->contactHref,
            'detailsHref' => $this->detailsHref,
            'phoneHref' => $this->phoneHref,
            'emailHref' => $this->emailHref,
            'whatsappHref' => $this->whatsappHref,
            'sections' => array_map(fn (LinkSection $section): array => $section->toArray(), $this->sections),
            'socialHeading' => $this->socialHeading,
            'socials' => array_map(fn (LinkItem $link): array => $link->toArray(), $this->socials),
            'heatmap' => $this->heatmap,
            'coffees' => $this->coffees,
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
