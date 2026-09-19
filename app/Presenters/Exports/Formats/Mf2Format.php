<?php

namespace App\Presenters\Exports\Formats;

use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportLink;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Support\PortableText;

/**
 * The export as microformats2 JSON. Must agree with the h-entry Entry.vue
 * renders: a parser reading both must get one answer, not two.
 */
final class Mf2Format extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Mf2;
    }

    public function render(ExportData $data): string
    {
        $trail = $this->trail($data);
        $profiles = $this->meProfiles();
        $meUrls = array_values(array_map(fn (array $profile): string => $profile['href'], $profiles));

        return json_encode([
            'items' => [
                ['type' => ['h-entry'], 'properties' => $this->properties($data)],
                $this->representativeCard(),
            ],
            'rels' => array_filter([
                'alternate' => array_values($trail),
                'me' => $meUrls,
            ]),
            'rel-urls' => [...$this->relUrls($trail), ...$this->meRelUrls($profiles)],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Header properties (url, uid, author, name, published) publish
     * regardless of lock state, matching the page: Entry.vue keeps its
     * header while the body is locked. Everything else is withheld for a
     * locked export, checked here rather than trusted from the caller.
     *
     * @return array<string, array<int, mixed>>
     */
    private function properties(ExportData $data): array
    {
        $properties = [
            'url' => [$data->url],
            'uid' => [$data->url],
            'author' => [$this->bareAuthor()],
        ];

        // A note has no name of its own: its body is the entry, so a
        // generated title would just restate it. Entry.vue withholds p-name
        // for the same reason, and the two must not disagree.
        if ($data->type !== TimelineType::Note) {
            $properties['name'] = [$data->title];
        }

        if ($data->occurred !== null) {
            $properties['published'] = [$data->occurred->iso];
        }

        if ($data->locked) {
            return $properties;
        }

        // Not $data->summary: that falls back to a generated description for
        // formats that always want one. p-summary publishes only a genuine
        // standfirst, because that is the only kind the page itself ever
        // shows (see ExportData's docblock for the distinction).
        if ($data->standfirst !== null) {
            $properties['summary'] = [$data->standfirst];
        }

        $html = $this->contentHtml($data);

        if ($html !== '') {
            $properties['content'] = [[
                'html' => $html,
                'value' => $this->contentText($data),
            ]];
        }

        $categories = array_map(fn (ExportLink $link): string => $link->title, $data->linksWithRel('category'));

        if ($categories !== []) {
            $properties['category'] = $categories;
        }

        $syndication = array_map(fn (ExportLink $link): string => $link->url, $data->linksWithRel('syndication'));

        if ($syndication !== []) {
            $properties['syndication'] = $syndication;
        }

        return [...$properties, ...$this->vocabulary($data)];
    }

    /**
     * The entry's nested p-author, matching AuthorRef.vue exactly: a bare
     * h-card carrying only a name and the site root, nothing else. The full
     * details live once, on the representative h-card below; a parser
     * resolves this to that card by matching the url, the same IndieWeb
     * authorship pattern the page itself uses.
     *
     * @return array{type: array<int, string>, properties: array<string, array<int, string>>}
     */
    private function bareAuthor(): array
    {
        return [
            'type' => ['h-card'],
            'properties' => [
                'name' => [config('identity.name')],
                'url' => [$this->siteUrl()],
            ],
        ];
    }

    /**
     * The owner's h-card, read from config/identity.php: the same source
     * ProfileCard.vue and app.blade.php render from, so a parse of the page
     * and this export describe one identity. The only place the full
     * details (photo, note) appear; the entry's nested author stays bare.
     *
     * @return array{type: array<int, string>, properties: array<string, array<int, mixed>>}
     */
    private function representativeCard(): array
    {
        $url = $this->siteUrl();
        $urls = [$url, ...array_map(fn (array $profile): string => $profile['href'], $this->meProfiles())];

        return [
            'type' => ['h-card'],
            'properties' => [
                'photo' => [['value' => url(config('identity.avatar')), 'alt' => config('identity.name')]],
                'name' => [config('identity.name')],
                'url' => $urls,
                'uid' => [$url],
                'note' => [config('identity.bio')],
            ],
        ];
    }

    /** The site root, with the trailing slash a browser normalises href="/" to. */
    private function siteUrl(): string
    {
        return url('/').'/';
    }

    /**
     * rel="me" profiles with somewhere to point: a '#' placeholder is not yet
     * claimed, and asserting rel="me" to it would be a claim about nothing.
     * Mirrors identityProfiles in resources/js/lib/identity.js.
     *
     * @return list<array{label: string, href: string}>
     */
    private function meProfiles(): array
    {
        return array_values(array_filter(
            config('identity.profiles'),
            fn (array $profile): bool => $profile['href'] !== '#',
        ));
    }

    /**
     * @param  list<array{label: string, href: string}>  $profiles
     * @return array<string, array<string, mixed>>
     */
    private function meRelUrls(array $profiles): array
    {
        $urls = [];

        foreach ($profiles as $profile) {
            $urls[$profile['href']] = [
                'rels' => ['me'],
                'text' => $profile['label'],
            ];
        }

        return $urls;
    }

    private function contentHtml(ExportData $data): string
    {
        return is_string($data->body) ? e($data->body) : PortableText::html($data->body);
    }

    private function contentText(ExportData $data): string
    {
        return is_string($data->body) ? $data->body : PortableText::text($data->body);
    }

    /**
     * The IndieWeb extension for a type that has one. Nothing is invented for a
     * type that does not.
     *
     * @return array<string, array<int, mixed>>
     */
    private function vocabulary(ExportData $data): array
    {
        return match ($data->type) {
            TimelineType::Event, TimelineType::Appearance => $this->event($data),
            TimelineType::Place => $this->checkin($data),
            TimelineType::Film, TimelineType::TvEpisode => $this->cite($data, 'watch-of'),
            TimelineType::Book => $this->cite($data, 'read-of'),
            default => [],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function event(ExportData $data): array
    {
        $span = $data->aspect(Span::class);

        if ($span === null) {
            return [];
        }

        return ['event' => [[
            'type' => ['h-event'],
            'properties' => array_filter([
                'name' => [$data->title],
                'start' => [$span->start->toIso8601String()],
                'end' => [$span->end->toIso8601String()],
                'location' => $span->location === null ? null : [$span->location],
            ]),
        ]]];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function checkin(ExportData $data): array
    {
        $location = $data->field('location');

        if ($location === null || ! is_array($location->raw)) {
            return [];
        }

        return ['checkin' => [[
            'type' => ['h-card'],
            'properties' => array_filter([
                'name' => [$data->title],
                'latitude' => isset($location->raw['lat']) ? [(string) $location->raw['lat']] : null,
                'longitude' => isset($location->raw['lng']) ? [(string) $location->raw['lng']] : null,
            ]),
        ]]];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function cite(ExportData $data, string $property): array
    {
        $rating = $data->field('rating');

        return array_filter([
            $property => [[
                'type' => ['h-cite'],
                'properties' => ['name' => [$data->title]],
            ]],
            'rating' => $rating === null ? null : [$rating->display],
        ]);
    }

    /**
     * Every other format this export supports, extension to absolute url:
     * the alternates a microformats2 parser is pointed at. Self-computed
     * rather than handed in, since mf2 is now the only format that needs one.
     *
     * @return array<string, string>
     */
    private function trail(ExportData $data): array
    {
        $trail = [];

        foreach (Formats::for($data) as $key => $format) {
            if (! $format instanceof self) {
                $trail[$key] = $data->url.'.'.$key;
            }
        }

        return $trail;
    }

    /**
     * @param  array<string, string>  $trail
     * @return array<string, array<string, mixed>>
     */
    private function relUrls(array $trail): array
    {
        $urls = [];

        foreach ($trail as $extension => $url) {
            $urls[$url] = [
                'rels' => ['alternate'],
                'type' => ExportFormat::from($extension)->contentType(),
            ];
        }

        return $urls;
    }
}
