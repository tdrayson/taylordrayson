<?php

namespace App\Data\LinkPage;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One link on a link-in-bio card: a row or tile with a logo image or a named icon.
 */
final readonly class LinkItem implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $label,
        public string $href,
        public ?string $description = null,
        public ?string $logo = null,
        public ?string $icon = null,
    ) {}

    /**
     * @return array{label: string, href: string, description: ?string, logo: ?string, icon: ?string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'href' => $this->href,
            'description' => $this->description,
            'logo' => $this->logo,
            'icon' => $this->icon,
        ];
    }

    /**
     * @return array{label: string, href: string, description: ?string, logo: ?string, icon: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
