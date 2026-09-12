<?php

use Tests\Support\DatasetSnapshot;

it('keeps every registry output identical to the pre-refactor snapshot', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/datasets-parity.json')), true);

    expect(json_decode(json_encode(DatasetSnapshot::build()), true))->toBe($fixture);
});
