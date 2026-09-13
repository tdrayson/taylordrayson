<?php

use App\Models\Food;
use App\Models\Note;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('reserves the bare food slug so a same-day note never holds it', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-03-15 09:00:00', 'content' => PortableText::fromPlainText('Food')]);

    expect($note->fresh()->url())->toBe('/2026/03/15/food-2');

    get($note->fresh()->url())->assertSuccessful();
});

it('gives a food entry logged afterwards the bare food slug', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-03-15 09:00:00', 'content' => PortableText::fromPlainText('Food')]);
    $food = Food::factory()->create(['occurred_at' => '2026-03-15 12:00:00']);

    expect($note->fresh()->url())->toBe('/2026/03/15/food-2')
        ->and($food->url())->toBe('/2026/03/15/food');

    get($note->fresh()->url())->assertSuccessful();
    get($food->url())->assertSuccessful();
});
