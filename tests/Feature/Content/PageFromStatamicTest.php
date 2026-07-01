<?php

use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

it('renders a page from a statamic entry', function () {
    Entry::make()->collection('pages')->slug('colophon')
        ->data(['title' => 'Colophon', 'excerpt' => 'About', 'content' => 'Hello'])
        ->save();

    $this->get('/colophon')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Page')->where('title', 'Colophon'));
});

it('404s a draft page for guests', function () {
    Entry::make()->collection('pages')->slug('secret')
        ->data(['title' => 'Secret'])->published(false)->save();
    $this->get('/secret')->assertNotFound();
});
