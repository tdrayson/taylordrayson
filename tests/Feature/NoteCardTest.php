<?php

use App\Models\Note;
use App\Presenters\CardPresenter;

it('ends a long note title on a whole word with an ellipsis character', function () {
    $note = Note::factory()->create([
        'content' => 'Walked the long way round the reservoir this morning and the herons were out in force again, all six of them',
    ]);

    expect(CardPresenter::for($note)->title)
        ->toBe('Walked the long way round the reservoir this morning and the herons were out in…');
});
