<?php

use App\Models\Calorie;

it('strips thousands separators from numeric values but keeps commas in text', function () {
    $csv = tempnam(sys_get_temp_dir(), 'cal').'.csv';
    file_put_contents($csv, "occurred_at,name,meal,quantity,units,calories\n2024-03-11T12:00,\"Biscuit, Rich Tea\",lunch,4,Pieces,\"1,088\"\n");

    $this->artisan('import:csv', ['file' => $csv, 'type' => 'calorie'])->assertExitCode(0);

    $calorie = Calorie::first();

    expect((int) $calorie->calories)->toBe(1088);
    expect((string) $calorie->calories)->not->toContain(',');
    expect($calorie->name)->toBe('Biscuit, Rich Tea');

    @unlink($csv);
});
