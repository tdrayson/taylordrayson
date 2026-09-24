<?php

use App\Enums\ExportFormat;
use App\Models\Activity;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints an activity as a stats card, with a bar for effort against max heart rate', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-09-13 13:16:01',
        'type' => 'run',
        'name' => 'Afternoon Run',
        'duration' => 1492,
        'distance' => 1677,
        'calories' => 107,
        'average_heart_rate' => 142,
        'max_heart_rate' => 180,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($activity);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Afternoon Run')
        ->and($txt)->toContain('107 kcal')
        ->and($txt)->toContain('142 bpm')
        ->and($txt)->toContain('180 bpm')
        ->and($txt)->toContain('79%')
        // 0.789 is the raw average/max fraction; the sheet must print "79%".
        ->and($txt)->not->toContain('0.789');
});

it('omits the distance and pace rows when an activity carries no distance', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-09-13 19:00:00',
        'type' => 'workout',
        'name' => 'Evening Tennis',
        'duration' => 3900,
        'distance' => null,
        'calories' => 433,
        'meta' => [],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($activity);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('DURATION')
        ->and($txt)->toContain('CALORIES')
        ->and($txt)->not->toContain('DISTANCE')
        ->and($txt)->not->toContain('PACE');
});

it('omits the heart rate bar when an activity carries no heart rate readings', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-09-13 13:16:01',
        'type' => 'walk',
        'average_heart_rate' => null,
        'max_heart_rate' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($activity);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('HEART RATE')
        ->and($txt)->not->toContain('#');
});
