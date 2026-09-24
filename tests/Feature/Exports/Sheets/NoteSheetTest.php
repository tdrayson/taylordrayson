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

it('keeps every line within width when the body ends with an emoji', function () {
    // The reported case: a display-width character measured as one code point
    // widened its box row past the sheet width.
    $note = Note::factory()->create([
        'content' => "Having my Surgeon come up to me in Nando's is a very weird experience.\n\n".
            "It's weird to think these people live an actual life outside of the hospital 😅",
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($note);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('😅');

    foreach (explode("\n", $txt) as $line) {
        expect(mb_strwidth($line))->toBeLessThanOrEqual(46);
    }
});

it('draws an empty ruled card when a note has no body', function () {
    $note = Note::factory()->create(['content' => [], 'status' => 'published']);

    $data = ExportPresenter::for($note);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('+--')
        ->and($txt)->not->toContain('| ');
});
