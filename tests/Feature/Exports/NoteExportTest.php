<?php

use App\Models\Note;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a note with no fields, only its body', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-09-13 07:00:00',
        'content' => 'Woke up early and went for a run.',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($note);

    expect($export->fields)->toBe([])
        ->and($export->body)->toBe($note->content);

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('type', 'day');
});

it('offers neither geojson nor ics for a note, since it has no aspects', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-09-13 07:00:00', 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($note)));

    expect($formats)->not->toContain('geojson')
        ->not->toContain('ics');
});

it('withholds p-name from a note in mf2, since a note has no title of its own', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-09-13 07:00:00', 'status' => 'published']);

    $mf2 = json_decode(Formats::for(ExportPresenter::for($note))['mf2']->render(ExportPresenter::for($note)), true);

    expect($mf2['items'][0]['properties'])->not->toHaveKey('name');
});

it('never leaks an id, a timestamp or a password', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-09-13 07:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($note)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
