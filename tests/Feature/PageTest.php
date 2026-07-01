<?php

use App\Models\User;
use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

it('renders a published page at its slug', function () {
    Entry::make()->collection('pages')->slug('about')
        ->data(['title' => 'About me'])->save();

    $this->get('/about')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Page')->where('title', 'About me'));
});

it('hides a draft page from guests but shows it to authenticated users', function () {
    Entry::make()->collection('pages')->slug('secret')
        ->data(['title' => 'Secret'])->published(false)->save();

    $this->get('/secret')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/secret')->assertSuccessful();
});

it('404s an unknown slug', function () {
    $this->get('/nope-not-here')->assertNotFound();
});

it('does not shadow an explicit route with a same-slug page', function () {
    Entry::make()->collection('pages')->slug('sleep-score')
        ->data(['title' => 'Hijack attempt'])->save();

    $this->get('/sleep-score')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('SleepScore'));
});
