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
     * @param  bool  $work  Whether the phone, email and website are work details rather than personal ones.
     * @param  string|null  $birthday  A Y-m-d date.
     * @param  array<string, string>  $profiles  Labelled URLs (socials, WhatsApp) keyed by their label.
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
        public bool $work = false,
        public ?string $birthday = null,
        public array $profiles = [],
        public ?string $photoPath = null,
    ) {}

    /** The download filename: taylor-drayson.vcf, or taylor-drayson-the-creative-tinker.vcf for work. */
    public function filename(): string
    {
        return str($this->work ? $this->name.' '.$this->organisation : $this->name)->slug()->append('.vcf')->toString();
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
            'work' => $this->work,
            'birthday' => $this->birthday,
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
