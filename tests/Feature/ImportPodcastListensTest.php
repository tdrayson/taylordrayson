<?php

use App\Support\PocketCasts;

use function Pest\Laravel\mock;

it('builds a listens csv from the export, enriching via the Pocket Casts API', function () {
    $dir = sys_get_temp_dir().'/listens_'.uniqid();
    mkdir($dir);
    $export = "{$dir}/data.txt";
    $csv = "{$dir}/listens.csv";

    file_put_contents($export, implode("\n", [
        'Email: x',
        '',
        'Episodes',
        '--------',
        'uuid,playing status,played up to,deleted?,duration,starred,modified',
        'ep-played,3,1500,false,1800,true,2025-06-09T20:41:05.205Z',     // played, in recent history
        'ep-progress,2,300,false,2700,false,2026-04-07T10:37:36.014Z',   // in progress, resolved via a subscription
        'ep-unplayed,1,0,false,0,false,2020-01-01T00:00:00Z',            // unplayed -> excluded
        'ep-orphan,3,900,true,1200,false,2019-02-02T02:02:02Z',          // played but unresolvable -> blank metadata
        '',
        'Folders',
        '--------',
        'x',
    ])."\n");

    $mock = mock(PocketCasts::class);
    $mock->shouldReceive('history')->andReturn(['episodes' => [
        ['uuid' => 'ep-played', 'title' => 'Played Ep', 'podcastTitle' => 'Show A', 'author' => 'Auth A', 'published' => '2025-06-01T00:00:00Z', 'duration' => 1800, 'url' => 'http://a', 'podcastUuid' => 'pod-a'],
    ]]);
    $mock->shouldReceive('inProgress')->andReturn(['episodes' => []]);
    $mock->shouldReceive('starred')->andReturn(['episodes' => []]);
    $mock->shouldReceive('subscriptions')->andReturn(['podcasts' => [
        ['uuid' => 'pod-b', 'title' => 'Show B', 'author' => 'Auth B'],
    ]]);
    $mock->shouldReceive('podcast')->with('pod-b')->andReturn(['podcast' => [
        'title' => 'Show B',
        'author' => 'Auth B',
        'episodes' => [
            ['uuid' => 'ep-progress', 'title' => 'Progress Ep', 'published' => '2026-04-01T00:00:00Z', 'duration' => 2700, 'url' => 'http://b'],
        ],
    ]]);

    $this->artisan('podcast:listens', ['file' => $export, '--csv' => $csv])->assertSuccessful();

    $rows = array_map(fn (string $line): array => str_getcsv($line, ',', '"', '\\'), file($csv, FILE_IGNORE_NEW_LINES));

    expect($rows[0])->toBe(['occurred_at', 'status', 'title', 'show', 'author', 'published', 'duration', 'played_up_to', 'starred', 'url', 'podcast_uuid', 'episode_uuid']);

    $byUuid = collect($rows)->skip(1)->keyBy(fn (array $row): string => $row[11]);

    // Unplayed excluded; the other three kept.
    expect($byUuid)->toHaveCount(3)
        ->and($byUuid)->toHaveKeys(['ep-played', 'ep-progress', 'ep-orphan'])
        ->and($byUuid)->not->toHaveKey('ep-unplayed');

    // Enriched from history.
    expect($byUuid['ep-played'][1])->toBe('played')
        ->and($byUuid['ep-played'][2])->toBe('Played Ep')
        ->and($byUuid['ep-played'][3])->toBe('Show A')
        ->and($byUuid['ep-played'][8])->toBe('1'); // starred

    // Enriched from a subscription's episode list.
    expect($byUuid['ep-progress'][1])->toBe('in-progress')
        ->and($byUuid['ep-progress'][2])->toBe('Progress Ep')
        ->and($byUuid['ep-progress'][3])->toBe('Show B');

    // Unresolvable episode is still recorded, with blank metadata but its own data intact.
    expect($byUuid['ep-orphan'][2])->toBe('')
        ->and($byUuid['ep-orphan'][3])->toBe('')
        ->and($byUuid['ep-orphan'][6])->toBe('1200')   // duration from the export
        ->and($byUuid['ep-orphan'][7])->toBe('900');   // played up to

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});
