<?php

use App\Enums\EntryStatus;
use App\Enums\WebmentionKind;
use App\Mcp\Servers\SiteServer;
use App\Mcp\Tools\Conversation;
use App\Mcp\Tools\DataFreshness;
use App\Mcp\Tools\Entry;
use App\Mcp\Tools\EntrySeries;
use App\Mcp\Tools\NeedsAttention;
use App\Mcp\Tools\RecentResponses;
use App\Mcp\Tools\SearchEntries;
use App\Mcp\Tools\SearchFields;
use App\Mcp\Tools\Stats;
use App\Mcp\Tools\Timeline;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Food;
use App\Models\Place;
use App\Models\Sleep;
use App\Models\SyndicatedResponse;
use App\Models\User;

/*
 * The purpose-built tools, which exist so that answering an ordinary question
 * does not require writing SQL against a schema the caller has to learn first.
 */

function aNight(string $date = '2026-08-26', array $overrides = []): Sleep
{
    return Sleep::factory()->create([
        'occurred_at' => $date.' 07:00:00',
        'started_at' => $date.' 00:00:00',
        'duration' => 25200,
        'awake' => 600,
        'score' => 88,
        'duration_score' => 46,
        'bedtime_score' => 28,
        'interruption_score' => 14,
        'stages' => array_fill(0, 60, ['stage' => 'core', 'start' => $date.' 01:00:00', 'end' => $date.' 01:30:00']),
        ...$overrides,
    ]);
}

it('reports whether each kind of data is still arriving', function () {
    aNight('2026-08-26');

    $sleep = collect(callTool(DataFreshness::class)['data']['types'])->firstWhere('type', 'sleep');

    expect($sleep['entries'])->toBe(1)
        ->and($sleep['newest'])->toBe('2026-08-26 07:00:00')
        ->and($sleep)->toHaveKeys(['behind', 'recorded', 'recorded_after']);
});

it('breaks a tie between same-timestamp entries by the latest created_at', function () {
    // Inserted in this order (lower id first) so a query with no explicit
    // order would default to id order and pick the wrong, older-recorded row.
    $newer = aNight('2026-08-26');
    $older = aNight('2026-08-26');

    $newer->timelineEntry->forceFill(['created_at' => now()])->save();
    $older->timelineEntry->forceFill(['created_at' => now()->subDay()])->save();

    $sleep = collect(callTool(DataFreshness::class)['data']['types'])->firstWhere('type', 'sleep');

    expect($sleep['recorded'])->toBe($newer->timelineEntry->created_at->toDateTimeString());
});

it('counts a day of food as one entry, not one per item', function () {
    Food::factory()->count(6)->create(['occurred_at' => '2026-08-26 12:00:00']);

    $food = collect(callTool(DataFreshness::class)['data']['types'])->firstWhere('type', 'food');

    // Counting the model reported one entry per mouthful, which inflated food
    // tenfold and every total built on it.
    expect($food['entries'])->toBe(1);
});

it('returns a range of entries as cards', function () {
    aNight('2026-08-24');
    aNight('2026-08-26');

    $result = callTool(Timeline::class, ['from' => '2026-08-25', 'to' => '2026-08-27']);

    expect($result['data']['count'])->toBe(1)
        ->and($result['data']['entries'][0]['type'])->toBe('sleep')
        ->and($result['data']['entries'][0]['url'])->toContain('2026/08/26');
});

describe('status', function () {
    beforeEach(function () {
        // The MCP route is behind auth:api, so every real call is the owner's.
        $this->actingAs(User::factory()->create());

        aNight('2026-08-26');
        aNight('2026-08-26', ['status' => EntryStatus::Unlisted]);
        aNight('2026-08-26', ['status' => EntryStatus::Private, 'password' => 'secret']);
    });

    it('lists only published entries on the timeline unless asked', function () {
        $published = callTool(Timeline::class, ['from' => '2026-08-26']);
        $private = callTool(Timeline::class, ['from' => '2026-08-26', 'status' => 'private']);
        $all = callTool(Timeline::class, ['from' => '2026-08-26', 'status' => 'all']);

        expect(collect($published['data']['entries'])->pluck('status')->all())->toBe(['published'])
            ->and(collect($private['data']['entries'])->pluck('status')->all())->toBe(['private'])
            ->and($all['data']['count'])->toBe(3);
    });

    it('searches only published entries unless asked', function () {
        $filter = [['type' => 'sleep', 'conditions' => [['field' => 'duration', 'operator' => 'gt', 'value' => 1]]]];

        expect(callTool(SearchEntries::class, ['filter' => $filter])['data']['total'])->toBe(1)
            ->and(callTool(SearchEntries::class, ['filter' => $filter, 'status' => 'unlisted'])['data']['total'])->toBe(1)
            ->and(callTool(SearchEntries::class, ['filter' => $filter, 'status' => 'all'])['data']['total'])->toBe(3);
    });

    it('lets status conditions in the filter decide only when asked for all', function () {
        $filter = [
            ['type' => 'sleep', 'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'private']]],
            ['type' => 'activity', 'conditions' => [['field' => 'distance', 'operator' => 'gt', 'value' => 0]]],
        ];
        Activity::factory()->create(['status' => EntryStatus::Unlisted, 'distance' => 5000]);

        // Without all, the published default would silently empty the private group.
        expect(callTool(SearchEntries::class, ['filter' => $filter])['error'])->toBeTrue()
            ->and(callTool(SearchEntries::class, ['filter' => $filter, 'status' => 'all'])['data']['total'])->toBe(2);
    });

    it('finds drafts off the models, since they have no timeline row', function () {
        Article::factory()->draft()->create(['title' => 'Half a thought', 'occurred_at' => null]);
        Article::factory()->create(['title' => 'A whole thought']);

        $filter = [['type' => 'article', 'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'thought']]]];
        $drafts = callTool(SearchEntries::class, ['filter' => $filter, 'status' => 'draft'])['data'];
        $all = callTool(SearchEntries::class, ['filter' => $filter, 'status' => 'all'])['data'];

        expect($drafts['total'])->toBe(1)
            ->and($drafts['drafts'][0]['title'])->toBe('Half a thought')
            ->and($drafts['drafts'][0]['url'])->toStartWith('/drafts/article/')
            ->and($all['total'])->toBe(1)
            ->and($all['drafts'])->toHaveCount(1);
    });

    it('opens a draft by its drafts url', function () {
        $draft = Article::factory()->draft()->create(['occurred_at' => null]);

        $entry = callTool(Entry::class, ['url' => $draft->url()])['data'];

        expect($entry['status'])->toBe('draft');
    });
});

it('refuses a type that does not exist', function () {
    expect(callTool(Timeline::class, ['from' => '2026-08-26', 'type' => 'nonsense'])['error'])->toBeTrue();
});

describe('one entry', function () {
    it('carries what the card leaves out', function () {
        $night = aNight();

        $entry = callTool(Entry::class, ['url' => $night->url()])['data'];

        // The score components are columns the timeline card never shows.
        expect($entry['score'])->toBe(88)
            ->and($entry['duration_score'])->toBe(46)
            ->and($entry['type'])->toBe('sleep');
    });

    it('names its series and their sizes without returning them', function () {
        $night = aNight();

        $entry = callTool(Entry::class, ['url' => $night->url()]);

        expect($entry['data']['series'])->toHaveKey('stages')
            ->and($entry['data']['series']['stages'])->toBeGreaterThan(1000)
            // Named and measured only: the column itself must not be in the
            // payload, and the whole response stays smaller than it would be.
            ->and($entry['data'])->not->toHaveKey('stages')
            ->and(strlen($entry['text']))->toBeLessThan($entry['data']['series']['stages']);
    });

    it('fetches one series when it is asked for by name', function () {
        $night = aNight();

        $series = callTool(EntrySeries::class, ['url' => $night->url(), 'series' => 'stages']);

        expect($series['data']['values'])->toHaveCount(60);
    });

    it('says which series exist when asked for one that does not', function () {
        $night = aNight();

        $series = callTool(EntrySeries::class, ['url' => $night->url(), 'series' => 'track']);

        expect($series['error'])->toBeTrue()
            ->and($series['text'])->toContain('stages');
    });

    it('renders an entry in an export format', function () {
        $article = Article::factory()->create(['title' => 'On maps']);

        $markdown = callTool(Entry::class, ['url' => $article->url(), 'format' => 'md']);

        expect($markdown['error'])->toBeFalse()
            ->and($markdown['text'])->toContain('On maps');
    });

    it('says which formats an entry has when asked for one it does not', function () {
        $result = callTool(Entry::class, ['url' => aNight()->url(), 'format' => 'geojson']);

        expect($result['error'])->toBeTrue()
            ->and($result['text'])->toContain('md');
    });

    it('refuses a url with no entry behind it', function () {
        expect(callTool(Entry::class, ['url' => '/2026/01/01/nothing'])['error'])->toBeTrue();
    });

    // A model's own `type` column (a place's category, an activity's
    // discipline) must never overwrite the dataset key the card already put in
    // `type`; it is exposed as `kind` instead.
    it('keeps the dataset key as type and exposes a place\'s own type as kind', function () {
        $place = Place::factory()->create(['type' => 'Coffee Shop']);

        $entry = callTool(Entry::class, ['url' => $place->url()])['data'];

        expect($entry['type'])->toBe('place')
            ->and($entry['kind'])->toBe('Coffee Shop');
    });

    it('keeps the dataset key as type and exposes an activity\'s own type as kind', function () {
        $activity = Activity::factory()->create(['type' => 'run']);

        $entry = callTool(Entry::class, ['url' => $activity->url()])['data'];

        expect($entry['type'])->toBe('activity')
            ->and($entry['kind'])->toBe('run');
    });
});

describe('search', function () {
    it('describes what can be filtered on', function () {
        $sleep = callTool(SearchFields::class, ['type' => 'sleep'])['data'];

        expect($sleep['type'])->toBe('sleep')
            ->and(collect($sleep['fields'])->pluck('key'))->toContain('duration')
            ->and(collect($sleep['fields'])->firstWhere('key', 'duration')['operators'])->toContain('gt');
    });

    it('treats a retired dataset key as unknown', function () {
        $result = callTool(SearchFields::class, ['type' => 'podcast']);

        expect($result['error'])->toBeTrue()
            ->and($result['text'])->toContain('No searchable type called podcast');
    });

    it('still errors on an unknown type', function () {
        $result = callTool(SearchFields::class, ['type' => 'made-up']);

        expect($result['error'])->toBeTrue()
            ->and($result['text'])->toContain('No searchable type called made-up');
    });

    it('runs a structured filter', function () {
        aNight('2026-08-26', ['duration' => 30000]);
        aNight('2026-08-24', ['duration' => 10000]);

        $result = callTool(SearchEntries::class, [
            'filter' => [[
                'type' => 'sleep',
                'conditions' => [['field' => 'duration', 'operator' => 'gt', 'value' => 20000]],
            ]],
        ]);

        expect($result['data']['total'])->toBe(1);
    });

    // The same whitelist the web search uses, so an invented field is dropped
    // rather than reaching the compiler.
    it('refuses a filter with nothing usable in it', function () {
        $result = callTool(SearchEntries::class, [
            'filter' => [['type' => 'sleep', 'conditions' => [['field' => 'made_up', 'operator' => 'gt', 'value' => 1]]]],
        ]);

        expect($result['error'])->toBeTrue();
    });
});

describe('responses', function () {
    function aKudos(Activity $activity, string $at): SyndicatedResponse
    {
        return SyndicatedResponse::factory()->create([
            'target_type' => $activity->getMorphClass(),
            'target_id' => $activity->id,
            'kind' => WebmentionKind::Like,
            'author_name' => 'Clare A.',
            'occurred_at' => $at,
        ]);
    }

    it('reads the conversation on one entry', function () {
        $activity = Activity::factory()->create();
        aKudos($activity, now()->subHour());

        $conversation = callTool(Conversation::class, ['url' => $activity->url()])['data'];

        expect($conversation['responses'])->toHaveCount(1)
            ->and($conversation['responses'][0]['authorName'])->toBe('Clare A.');
    });

    it('marks responses after since as new', function () {
        aKudos(Activity::factory()->create(['occurred_at' => now()->subDays(3)]), now()->subDays(3));
        aKudos(Activity::factory()->create(['occurred_at' => now()->subHour()]), now()->subHour());

        $responses = callTool(RecentResponses::class, ['since' => now()->subDay()->toDateTimeString()])['data']['responses'];

        expect(collect($responses)->pluck('isNew')->all())->toBe([true, false]);
    });

    it('lists what is waiting on a decision', function () {
        Article::factory()->draft()->create(['occurred_at' => null]);

        $items = callTool(NeedsAttention::class)['data']['items'];

        expect($items)->toHaveCount(1)
            ->and($items[0]['kind'])->toBe('draft')
            ->and($items[0])->not->toHaveKey('icon');
    });
});

it('totals a period for one type', function () {
    aNight('2026-08-26');

    $result = callTool(Stats::class, ['from' => '2026-08-01', 'to' => '2026-08-31', 'type' => 'sleep']);

    expect($result['error'])->toBeFalse()
        ->and($result['data'])->toBeArray();
});

it('errors on a retired dataset key for stats', function () {
    $result = callTool(Stats::class, ['from' => '2026-08-01', 'to' => '2026-08-31', 'type' => 'podcast']);

    expect($result['error'])->toBeTrue()
        ->and($result['text'])->toContain('No type called podcast');
});

// A tool whose schema will not build is invisible to a client rather than
// noisy, so every registered one is exercised here.
it('exposes every registered tool with a schema a client can read', function () {
    $tools = (new ReflectionClass(SiteServer::class))->getDefaultProperties()['tools'];

    expect($tools)->toHaveCount(15);

    foreach ($tools as $tool) {
        expect(app($tool)->toArray())->toHaveKeys(['name', 'description', 'inputSchema']);
    }
});
