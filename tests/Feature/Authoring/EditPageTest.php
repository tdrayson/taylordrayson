<?php

use App\Models\Page;
use App\Models\User;

it('offers the editor only to a signed-in visitor', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About', 'published' => true]);

    $this->get('/about?edit')->assertInertia(fn ($page) => $page->where('editing', false));

    $this->actingAs(User::factory()->create())
        ->get('/about?edit')
        ->assertInertia(fn ($page) => $page->where('editing', true));
});

it('sends the field definitions only when signed in', function () {
    Page::factory()->create(['slug' => 'about', 'published' => true]);

    $this->get('/about')->assertInertia(fn ($page) => $page->where('fields', []));

    $this->actingAs(User::factory()->create())
        ->get('/about')
        ->assertInertia(fn ($page) => $page->where(
            'fields',
            fn ($fields) => collect($fields)->pluck('name')->contains('content'),
        ));
});

it('saves an edit made in place', function () {
    $page = Page::factory()->create(['slug' => 'about', 'title' => 'About', 'published' => true]);

    $this->actingAs(User::factory()->create())
        ->patch("/entries/page/{$page->id}", [
            'title' => 'About me',
            'content' => [[
                '_type' => 'block',
                '_key' => 'b1',
                'style' => 'normal',
                'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'Edited.', 'marks' => []]],
            ]],
        ])
        ->assertRedirect();

    expect($page->fresh()->title)->toBe('About me')
        ->and($page->fresh()->content[0]['children'][0]['text'])->toBe('Edited.');
});

it('refuses a save from a guest', function () {
    $page = Page::factory()->create(['slug' => 'about', 'title' => 'About', 'published' => true]);

    $this->patch("/entries/page/{$page->id}", ['title' => 'Hacked'])->assertRedirect('/login');

    expect($page->fresh()->title)->toBe('About');
});
