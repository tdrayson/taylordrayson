<?php

use App\Models\Food;
use App\Models\Note;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('gives a same-day note titled Food and the food entry distinct url slugs', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-03-15 09:00:00', 'content' => PortableText::fromPlainText('Food')]);
    $food = Food::factory()->create(['occurred_at' => '2026-03-15 12:00:00']);

    expect($note->fresh()->url())->toBe('/2026/03/15/food')
        ->and($food->url())->toBe('/2026/03/15/food-2');

    get($note->fresh()->url())->assertSuccessful();
    get($food->url())->assertSuccessful();
});
