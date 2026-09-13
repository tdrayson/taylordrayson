<?php

use App\Models\Activity;
use App\Models\Food;

use function Pest\Laravel\get;

it('redirects the old calories url to the food day entry', function () {
    $food = Food::factory()->create(['occurred_at' => '2026-03-15 12:00:00']);

    expect($food->url())->toBe('/2026/03/15/food');

    get('/2026/03/15/calories')->assertMovedPermanently()->assertRedirect('/2026/03/15/food');
});

it('404s the old calories url when the date has no food entry', function () {
    get('/2026/03/15/calories')->assertNotFound();
});

it('leaves an unrelated same-day entry resolving at its own url', function () {
    Food::factory()->create(['occurred_at' => '2026-03-15 12:00:00']);
    $activity = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);

    get($activity->url())->assertSuccessful();
    get('/2026/03/15/calories')->assertMovedPermanently()->assertRedirect('/2026/03/15/food');
});
