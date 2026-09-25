<?php

namespace App\Presenters;

use App\Data\LinkPage\ContactCard;
use App\Data\LinkPage\LinkItem;
use App\Data\LinkPage\LinkPageData;
use App\Data\LinkPage\LinkSection;
use App\Enums\LinkPage;
use App\Queries\CoffeesThisYear;
use App\Queries\RecentActivityLevels;
use App\Queries\ThisWeekWithEpisodeCount;
use Illuminate\Support\Str;

/**
 * Shapes a link-in-bio card from config/identity.php and config/profile.php.
 */
final class LinkPagePresenter
{
    // The personal header's grid: 7x3 tiles, less the 2x2 avatar and the theme toggle.
    private const HEATMAP_DAYS = 16;

    public function __construct(
        private readonly CoffeesThisYear $coffees,
        private readonly RecentActivityLevels $activityLevels,
        private readonly ThisWeekWithEpisodeCount $episodes,
    ) {}

    /**
     * The payload the card's Inertia page renders.
     */
    public function page(LinkPage $page): LinkPageData
    {
        $config = $this->config($page);
        $phone = $this->dialable($config['phone'] ?? null);
        $email = $config['email'] ?? null;

        return new LinkPageData(
            page: $page->value,
            name: config('identity.name'),
            organisation: $config['organisation'],
            bio: $config['bio'] ?? config('identity.bio'),
            avatar: config('identity.avatar'),
            contactHref: route('link-page.contact', $page, absolute: false),
            detailsHref: config('profile.details_form') ? route('link-page.details', $page, absolute: false) : null,
            phoneHref: $phone === null ? null : "tel:{$phone}",
            emailHref: filled($email) ? "mailto:{$email}" : null,
            whatsappHref: $phone === null ? null : 'https://wa.me/'.ltrim($phone, '+'),
            sections: array_map($this->section(...), $config['sections']),
            socialHeading: $config['social_heading'],
            socials: $this->socials($config['socials']),
            heatmap: $page === LinkPage::Personal ? ($this->activityLevels)(self::HEATMAP_DAYS) : [],
            coffees: ($this->coffees)(),
        );
    }

    /**
     * The details the card's vCard carries.
     */
    public function contact(LinkPage $page): ContactCard
    {
        $config = $this->config($page);
        $name = (string) config('identity.name');
        $photo = public_path(ltrim((string) config('identity.photo'), '/'));

        return new ContactCard(
            name: $name,
            givenName: Str::beforeLast($name, ' '),
            familyName: Str::contains($name, ' ') ? Str::afterLast($name, ' ') : '',
            organisation: $config['organisation'],
            title: $config['title'],
            phone: $this->dialable($config['phone'] ?? null),
            email: filled($config['email'] ?? null) ? $config['email'] : null,
            website: $config['website'],
            profiles: collect($this->socials($config['socials']))
                ->mapWithKeys(fn (LinkItem $profile): array => [$profile->icon => $profile->href])
                ->all(),
            photoPath: is_file($photo) ? $photo : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function config(LinkPage $page): array
    {
        return config("profile.pages.{$page->value}");
    }

    /**
     * @param  array{heading: string, links: list<array<string, string>>}  $section
     */
    private function section(array $section): LinkSection
    {
        return new LinkSection($section['heading'], array_map(fn (array $link): LinkItem => new LinkItem(
            label: $link['label'],
            href: $link['href'],
            description: isset($link['description'])
                ? str_replace(':episodes', number_format(($this->episodes)()), $link['description'])
                : null,
            logo: $link['logo'] ?? null,
            icon: $link['icon'] ?? null,
        ), $section['links']));
    }

    /**
     * The identity profiles this card shows, in the card's order, each icon named by its profile label.
     *
     * @param  array<string, string>  $labels  Identity profile label => the label shown on the card.
     * @return list<LinkItem>
     */
    private function socials(array $labels): array
    {
        $profiles = collect(config('identity.profiles'))->keyBy('label');

        return collect($labels)
            ->filter(fn (string $label, string $profile): bool => $profiles->has($profile))
            ->map(fn (string $label, string $profile): LinkItem => new LinkItem(
                label: $label,
                href: $profiles[$profile]['href'],
                icon: $profile,
            ))
            ->values()
            ->all();
    }

    /** A phone number with only its digits and leading +, e.g. +447700900000, or null when unset. */
    private function dialable(?string $phone): ?string
    {
        $number = preg_replace('/[^\d+]/', '', (string) $phone);

        return $number === '' ? null : $number;
    }
}
