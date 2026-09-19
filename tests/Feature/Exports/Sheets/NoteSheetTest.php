<?php

use App\Enums\ExportFormat;
use App\Models\Note;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use Illuminate\Support\Facades\Queue;

it('prints a note as its index card, with the body wrapped inside a ruled box', function () {
    $note = Note::factory()->create([
        'content' => 'Woke up early and went for a run before the heat set in.',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($note);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Woke up early and went for a run')
        ->and($txt)->toStartWith('+')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('keeps a pasted link intact on one line rather than cutting it mid-character', function () {
    Queue::fake();

    $url = 'https://example.com/a-very-long-path-segment-that-exceeds-forty-six-characters-total';

    $note = Note::factory()->create([
        'content' => "Check this out {$url} end.",
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($note);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain($url);
});

it('draws an empty ruled card when a note has no body', function () {
    $note = Note::factory()->create(['content' => [], 'status' => 'published']);

    $data = ExportPresenter::for($note);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('+--')
        ->and($txt)->not->toContain('| ');
});
