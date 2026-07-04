<?php

use App\Models\Page;
use App\Models\User;

it('renders a published page at its slug', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About me', 'published' => true]);

    $this->get('/about')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Page')->where('title', 'About me'));
});

it('hides a draft page from guests but shows it to authenticated users', function () {
    Page::factory()->draft()->create(['slug' => 'secret']);

    $this->get('/secret')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/secret')->assertSuccessful();
});

it('404s an unknown slug', function () {
    $this->get('/nope-not-here')->assertNotFound();
});

it('does not shadow an explicit route with a same-slug page', function () {
    Page::factory()->create(['slug' => 'sleep-score', 'title' => 'Hijack attempt']);

    $this->get('/sleep-score')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('SleepScore'));
});
