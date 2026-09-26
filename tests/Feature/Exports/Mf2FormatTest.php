<?php

use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportLink;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Formats\Mf2Format;
use App\Support\PortableText;
use Carbon\CarbonImmutable;

it('renders an h-entry with the properties a parser expects', function () {
    $data = ExportPresenter::for(krkToLgw());
    $mf2 = json_decode(Formats::find($data, ExportFormat::Mf2)->render($data), true);

    $item = $mf2['items'][0];
    $jsonUrl = $data->url.'.json';

    expect($item['type'])->toBe(['h-entry'])
        ->and($item['properties']['name'][0])->toBe($data->title)
        ->and($item['properties']['url'][0])->toBe($data->url)
        ->and($item['properties']['uid'][0])->toBe($data->url)
        ->and($item['properties']['published'][0])->toStartWith('2026-06-08')
        ->and($item['properties']['author'][0]['type'])->toBe(['h-card'])
        ->and($item['properties']['author'][0]['properties']['name'][0])->toBe('Taylor Drayson')
        // Self-computed from the export's own supported formats, so a parse of
        // this must agree with the page's own <link rel="alternate"> set.
        ->and($mf2['rels']['alternate'])->toContain($jsonUrl)
        ->and($mf2['rel-urls'][$jsonUrl]['type'])->toBe('application/json; charset=utf-8')
        // A flight's page has no p-summary anywhere, so the export must not
        // invent one from the generated meta description either.
        ->and($item['properties'])->not->toHaveKey('summary');
});

// AuthorRef.vue renders a bare h-card (name + site root, nothing else) inside
// the entry; the full details live once, on the representative h-card below.
// Carrying both would put the same card in the document twice.
it('nests a bare author on the h-entry, with the full card only as a second top-level item', function () {
    $data = ExportPresenter::for(krkToLgw());
    $mf2 = json_decode(Formats::find($data, ExportFormat::Mf2)->render($data), true);

    $author = $mf2['items'][0]['properties']['author'][0];
    $card = $mf2['items'][1];

    expect($author['type'])->toBe(['h-card'])
        ->and($author['properties'])->toBe([
            'name' => ['Taylor Drayson'],
            'url' => [config('app.url').'/'],
        ])
        ->and($card['type'])->toBe(['h-card'])
        ->and($card['properties']['photo'][0])->toContain(config('identity.photo'))
        ->and($card['properties']['name'][0])->toBe('Taylor Drayson')
        ->and($card['properties']['url'])->toBe([config('app.url').'/', 'https://github.com/tdrayson'])
        ->and($card['properties']['uid'][0])->toBe(config('app.url').'/')
        ->and($card['properties']['note'][0])->toBe(config('identity.bio'));
});

// The distinction a parser uses to tell a note from an article: Entry.vue
// withholds p-name for the same reason, and the two must not disagree.
// EntryDescription derives a note's summary from its own body (there is
// nothing else to write one from), so a populated summary here stands in for
// what the real export presenter would hand this format: a restatement of
// e-content, which withholding proves doesn't leak through.
it('omits p-name and p-summary for a note, matching the page', function () {
    $data = new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/notes/1',
        title: 'Just a thought',
        summary: 'Just a thought, logged.',
        occurred: null,
        fields: [],
        links: [],
        body: [PortableText::block('Just a thought, logged.')],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties)->not->toHaveKey('name')
        ->and($properties)->not->toHaveKey('summary')
        ->and($properties['content'][0]['value'])->toContain('Just a thought, logged.')
        ->and($properties['content'][0]['html'])->toContain('<p>');
});

// The rule is generic, not a type check: p-summary tracks $standfirst, not
// $summary. $summary here is deliberately populated (the way EntryDescription
// generates one for every type, for formats that always want a description)
// to prove a generated description alone is not enough to publish p-summary.
it('omits p-summary for any type with no standfirst, even with a generated summary present', function () {
    $data = new ExportData(
        type: TimelineType::Activity,
        url: 'https://example.test/activities/1',
        title: 'A run',
        summary: 'I ran 3 miles in 28 minutes.',
        occurred: null,
        fields: [],
        links: [],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties)->not->toHaveKey('summary');
});

it('publishes p-summary from standfirst, not from the generated summary, when both are set', function () {
    $data = new ExportData(
        type: TimelineType::Article,
        url: 'https://example.test/articles/1',
        title: 'An article',
        summary: 'A generated meta description nobody wrote by hand.',
        occurred: null,
        fields: [],
        links: [],
        standfirst: 'The hand-written excerpt.',
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties['summary'])->toBe(['The hand-written excerpt.']);
});

it('takes p-category from category links and u-syndication from syndication links', function () {
    $data = new ExportData(
        type: TimelineType::Article,
        url: 'https://example.test/articles/1',
        title: 'An article',
        summary: null,
        occurred: null,
        fields: [],
        links: [
            ExportLink::make('tag', 'Tagged', 'Travel', 'https://example.test/tags/travel', 'category'),
            ExportLink::make('tag', 'Tagged', 'Poland', 'https://example.test/tags/poland', 'category'),
            ExportLink::make('source', 'Source', 'Swarm', 'https://example.test/swarm/1', 'syndication'),
            ExportLink::make('day', 'That day', '8 June', '/2026/06/08'),
        ],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties['category'])->toBe(['Travel', 'Poland'])
        ->and($properties['syndication'])->toBe(['https://example.test/swarm/1']);
});

it('adds a nested h-event with start, end and location for an event or appearance', function (TimelineType $type) {
    $start = CarbonImmutable::parse('2026-06-08 20:00:00', 'Europe/London');
    $end = CarbonImmutable::parse('2026-06-08 22:00:00', 'Europe/London');

    $data = new ExportData(
        type: $type,
        url: 'https://example.test/events/1',
        title: 'A gig',
        summary: null,
        occurred: null,
        fields: [],
        links: [],
        aspects: [Span::class => Span::across($start, $end, 'The venue')],
    );

    $event = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties']['event'][0];

    expect($event['type'])->toBe(['h-event'])
        ->and($event['properties']['start'][0])->toBe($start->toIso8601String())
        ->and($event['properties']['end'][0])->toBe($end->toIso8601String())
        ->and($event['properties']['location'][0])->toBe('The venue');
})->with([TimelineType::Event, TimelineType::Appearance]);

it('adds no h-event for an event with no span aspect', function () {
    $data = new ExportData(
        type: TimelineType::Event,
        url: 'https://example.test/events/1',
        title: 'A gig',
        summary: null,
        occurred: null,
        fields: [],
        links: [],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties)->not->toHaveKey('event');
});

it('adds a p-checkin h-card with latitude and longitude for a place', function () {
    $data = new ExportData(
        type: TimelineType::Place,
        url: 'https://example.test/places/1',
        title: 'A café',
        summary: null,
        occurred: null,
        fields: [ExportField::make('location', 'Location', 'A café', ['lat' => 51.5, 'lng' => -0.12])],
        links: [],
    );

    $checkin = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties']['checkin'][0];

    expect($checkin['type'])->toBe(['h-card'])
        ->and($checkin['properties']['latitude'][0])->toBe('51.5')
        ->and($checkin['properties']['longitude'][0])->toBe('-0.12');
});

it('adds u-watch-of and p-rating for a film or tv episode', function (TimelineType $type) {
    $data = new ExportData(
        type: $type,
        url: 'https://example.test/films/1',
        title: 'A film',
        summary: null,
        occurred: null,
        fields: [ExportField::make('rating', 'Rating', '4/5', 4)],
        links: [],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties['watch-of'][0]['type'])->toBe(['h-cite'])
        ->and($properties['watch-of'][0]['properties']['name'][0])->toBe('A film')
        ->and($properties['rating'][0])->toBe('4/5');
})->with([TimelineType::Film, TimelineType::TvEpisode]);

it('adds u-read-of and p-rating for a book', function () {
    $data = new ExportData(
        type: TimelineType::Book,
        url: 'https://example.test/books/1',
        title: 'A book',
        summary: null,
        occurred: null,
        fields: [ExportField::make('rating', 'Rating', '5/5', 5)],
        links: [],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties['read-of'][0]['type'])->toBe(['h-cite'])
        ->and($properties['read-of'][0]['properties']['name'][0])->toBe('A book')
        ->and($properties['rating'][0])->toBe('5/5');
});

it('adds no vocabulary extension for a type with no established one', function () {
    $data = new ExportData(
        type: TimelineType::Activity,
        url: 'https://example.test/activities/1',
        title: 'A run',
        summary: null,
        occurred: null,
        fields: [],
        links: [],
    );

    $properties = json_decode((new Mf2Format)->render($data), true)['items'][0]['properties'];

    expect($properties)->not->toHaveKey('event')
        ->and($properties)->not->toHaveKey('checkin')
        ->and($properties)->not->toHaveKey('watch-of')
        ->and($properties)->not->toHaveKey('read-of');
});
