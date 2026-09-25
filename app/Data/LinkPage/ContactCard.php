<?php

namespace App\Data\LinkPage;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The contact details a link-in-bio card hands out as a vCard.
 */
final readonly class ContactCard implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, string>  $profiles  Social profile URLs keyed by their label.
     * @param  string|null  $photoPath  Absolute path to a JPEG embedded as the contact photo.
     */
    public function __construct(
        public string $name,
        public string $givenName,
        public string $familyName,
        public ?string $organisation = null,
        public ?string $title = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public array $profiles = [],
        public ?string $photoPath = null,
    ) {}

    /** The download filename, e.g. taylor-drayson-the-creative-tinker.vcf. */
    public function filename(): string
    {
        return str($this->name.' '.$this->organisation)->slug()->append('.vcf')->toString();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'givenName' => $this->givenName,
            'familyName' => $this->familyName,
            'organisation' => $this->organisation,
            'title' => $this->title,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'profiles' => $this->profiles,
            'photoPath' => $this->photoPath,
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
