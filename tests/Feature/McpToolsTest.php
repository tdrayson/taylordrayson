<?php

use App\Mcp\Servers\SiteServer;
use App\Mcp\Tools\DataFreshness;
use App\Mcp\Tools\Entry;
use App\Mcp\Tools\EntrySeries;
use App\Mcp\Tools\SearchEntries;
use App\Mcp\Tools\SearchFields;
use App\Mcp\Tools\Stats;
use App\Mcp\Tools\Timeline;
use App\Models\Sleep;
use Laravel\Mcp\Request;

/*
 * The purpose-built tools, which exist so that answering an ordinary question
 * does not require writing SQL against a schema the caller has to learn first.
 */

/**
 * @return array{error: bool, data: mixed, text: string}
 */
function callTool(string $tool, array $arguments = []): array
{
    $response = app($tool)->handle(new Request($arguments));
    $text = $response->content()->toArray()['text'];

    return ['error' => $response->isError(), 'data' => json_decode($text, true), 'text' => $text];
}

function aNight(string $date = '2026-08-26', array $overrides = []): Sleep
{
    return Sleep::factory()->create([
        'occurred_at' => $date.' 07:00:00',
        'bedtime' => $date.' 00:00:00',
        'wake_time' => $date.' 07:00:00',
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

it('returns a range of entries as cards', function () {
    aNight('2026-08-24');
    aNight('2026-08-26');

    $result = callTool(Timeline::class, ['from' => '2026-08-25', 'to' => '2026-08-27']);

    expect($result['data']['count'])->toBe(1)
        ->and($result['data']['entries'][0]['type'])->toBe('sleep')
        ->and($result['data']['entries'][0]['url'])->toContain('2026/08/26');
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

    it('refuses a url with no entry behind it', function () {
        expect(callTool(Entry::class, ['url' => '/2026/01/01/nothing'])['error'])->toBeTrue();
    });
});

describe('search', function () {
    it('describes what can be filtered on', function () {
        $sleep = callTool(SearchFields::class, ['type' => 'sleep'])['data'];

        expect($sleep['type'])->toBe('sleep')
            ->and(collect($sleep['fields'])->pluck('key'))->toContain('duration')
            ->and(collect($sleep['fields'])->firstWhere('key', 'duration')['operators'])->toContain('gt');
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

it('totals a period for one type', function () {
    aNight('2026-08-26');

    $result = callTool(Stats::class, ['from' => '2026-08-01', 'to' => '2026-08-31', 'type' => 'sleep']);

    expect($result['error'])->toBeFalse()
        ->and($result['data'])->toBeArray();
});

// A tool whose schema will not build is invisible to a client rather than
// noisy, so every registered one is exercised here.
it('exposes every registered tool with a schema a client can read', function () {
    $tools = (new ReflectionClass(SiteServer::class))->getDefaultProperties()['tools'];

    expect($tools)->toHaveCount(11);

    foreach ($tools as $tool) {
        expect(app($tool)->toArray())->toHaveKeys(['name', 'description', 'inputSchema']);
    }
});
