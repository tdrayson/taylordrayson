<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('renders a page cover and offers it back to the editor', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'colophon', 'title' => 'Colophon', 'published' => true]);
    $page->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    $this->get('/colophon')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('cover.full'));

    // The picker opened empty over an attached image: a cover is a media
    // collection, so the model's attributes alone never mention it.
    $this->actingAs(User::factory()->create())
        ->get('/colophon?edit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('values.cover', 1));
});

it('has no cover to render when none is attached', function () {
    Page::factory()->create(['slug' => 'plain', 'published' => true]);

    $this->get('/plain')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cover', null));
});
