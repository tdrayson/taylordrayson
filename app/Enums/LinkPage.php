<?php

namespace App\Enums;

/**
 * The link-in-bio cards on the profile subdomain, valued by their URL segment.
 */
enum LinkPage: string
{
    case Personal = 'td';
    case Business = 'tct';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Business => 'Business',
        };
    }

    /** The Inertia page component that renders the card. */
    public function component(): string
    {
        return 'LinkPage/'.$this->label();
    }

    /** The Inertia page component for the card's "Send me your details" form. */
    public function detailsComponent(): string
    {
        return $this->component().'Details';
    }
}
