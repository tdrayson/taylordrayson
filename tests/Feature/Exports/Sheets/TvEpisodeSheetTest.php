<?php

use App\Enums\ExportFormat;
use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Sheets\TvEpisodeSheet;
use App\Support\SerialNumber;

it('prints a TV episode as a perforated ticket stub, headed by the show and paired season/number and date/rated rows', function () {
    $show = TvShow::factory()->create(['title' => 'Crime Scene: The Vanishing at the Cecil Hotel']);
    $episode = TvEpisode::factory()->create([
        'tv_show_id' => $show->id,
        'occurred_at' => '2026-09-13 20:00:00',
        'title' => 'Down the Rabbit Hole',
        'rating' => 8,
        'meta' => ['season' => 1, 'episode' => 3],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $rows = array_map(fn (array $r): array => [$r[0], $r[1]], ticketRows($txt));

    expect($txt)->toContain('CRIME SCENE: THE VANISHING AT THE CECIL HOTEL')
        ->and($txt)->not->toContain('ADMIT ONE')
        ->and($txt)->toContain('No. '.SerialNumber::for($episode->occurred_at, $episode->id))
        ->and($txt)->toContain('TAYLORDRAYSON')
        ->and($rows)->toContain(
            ['EPISODE', 'Down the Rabbit Hole'],
            ['SEASON', '1'],
            ['NUMBER', '3'],
            ['DATE', '13 Sep 2026'],
            ['RATED', '8 out of 10'],
        );
});

it('keeps every line the same width as its declared ticket width', function () {
    $episode = TvEpisode::factory()->create(['occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect(array_unique($widths))->toHaveCount(1);
});

it('widens the ticket rather than truncating a show name too long for the default width', function () {
    $show = TvShow::factory()->create(['title' => 'A Show With A Genuinely Very Long Title That Keeps On Going']);
    $episode = TvEpisode::factory()->create(['tv_show_id' => $show->id, 'occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect($txt)->toContain(mb_strtoupper($show->title))
        ->and(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBeGreaterThan(TvEpisodeSheet::WIDTH);
});

it('omits the rating from the paired row, but keeps the date, when a TV episode carries no rating', function () {
    $episode = TvEpisode::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'rating' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('out of 10')
        ->and(array_column(ticketRows($txt), 0))->toContain('DATE');
});

it('renders the same barcode for the same episode every time', function () {
    $episode = TvEpisode::factory()->create(['occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($episode);
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
