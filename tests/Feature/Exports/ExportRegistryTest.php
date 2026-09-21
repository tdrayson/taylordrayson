<?php

use App\Data\ExportData;
use App\Datasets\Datasets;

it('has a working export presenter for every dataset', function () {
    foreach (Datasets::all() as $key => $dataset) {
        // create(), not make(): several presenters touch relations (Flight's
        // airports, TvEpisode's show), and Event/Appearance need a real key
        // and spine row for url()'s draft branch, or they blow up on an
        // unhydrated model (the url column collision in #473).
        $model = $dataset->model()::factory()->create();

        $data = $dataset->export()->present($model);

        expect($data)->toBeInstanceOf(ExportData::class, "Dataset {$key}'s export did not return an ExportData")
            ->and($data->title)->not->toBeEmpty("Dataset {$key}'s export produced an empty title")
            ->and($data->type)->toBe($dataset->type(), "Dataset {$key}'s export reported the wrong type");
    }
});
