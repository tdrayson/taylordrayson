<?php

use App\Models\Event;

it('shows a multi-day event on a middle day of its range', function () {
    Event::factory()->create([
        'name' => 'WordCamp Europe',
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    $this->get('/2022/06/03')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Day')->has('items', 1));
});
