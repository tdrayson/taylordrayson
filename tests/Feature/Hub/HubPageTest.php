<?php

use App\Models\Note;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('sends real entry counts to the page', function () {
    Note::factory()->create(['occurred_at' => now()->subHour()]);

    actingAs(User::factory()->create());

    get('/hq')->assertInertia(
        fn ($page) => $page->component('Hub')
            ->where('entries', fn ($entries) => (function () use ($entries) {
                $notes = collect($entries)->first(
                    fn ($entry) => $entry['label'] === 'Notes'
                );

                return $notes !== null
                    && $notes['count'] === 1
                    && $notes['synced'] === false;
            })())
    );
});

it('is not reachable signed out', function () {
    get('/hq')->assertRedirect('/login');
});
