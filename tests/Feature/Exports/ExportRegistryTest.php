<?php

use App\Datasets\Datasets;

it('has an export presenter for every dataset', function () {
    foreach (Datasets::all() as $key => $dataset) {
        expect(method_exists($dataset->export(), 'present'))
            ->toBeTrue("Dataset {$key} has no export presenter");
    }
});
