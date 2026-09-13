<?php

use App\Datasets\Datasets;
use App\Enums\SpanAnchor;
use App\Models\Concerns\HasSpan;
use App\Models\Event;
use App\Models\Sleep;
use Illuminate\Support\Facades\Schema;

it('gives every spanned dataset the trait and the column for its other bound', function () {
    foreach (Datasets::all() as $key => $dataset) {
        $model = $dataset->model();
        $usesSpan = in_array(HasSpan::class, class_uses_recursive($model), true);
        $anchor = $dataset->spanAnchor();

        expect($usesSpan)->toBe($anchor !== null, "{$key}: trait and anchor must agree");

        if ($anchor !== null) {
            $column = $anchor === SpanAnchor::End ? 'started_at' : 'ends_at';

            expect(Schema::hasColumn((new $model)->getTable(), $column))->toBeTrue("{$key} needs {$column}");
        }
    }
});

it('reads a start-anchored span from occurred_at to ends_at', function () {
    $event = Event::factory()->make(['occurred_at' => '2026-06-04 18:00:00', 'ends_at' => '2026-06-06 23:00:00']);

    expect($event->spanStart()->toDateTimeString())->toBe('2026-06-04 18:00:00')
        ->and($event->spanEnd()->toDateTimeString())->toBe('2026-06-06 23:00:00');
});

it('reads an end-anchored span from started_at to occurred_at', function () {
    $sleep = Sleep::factory()->make(['started_at' => '2026-06-19 23:00:00', 'occurred_at' => '2026-06-20 07:00:00']);

    expect($sleep->spanStart()->toDateTimeString())->toBe('2026-06-19 23:00:00')
        ->and($sleep->spanEnd()->toDateTimeString())->toBe('2026-06-20 07:00:00');
});
