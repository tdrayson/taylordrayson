<?php

use Illuminate\Support\Facades\File;
use Statamic\Facades\Entry;

afterEach(function () {
    // Remove flat-file page entries created during this test to keep the
    // content directory clean. We delete from disk directly to avoid
    // triggering Statamic stache events that interfere with later tests.
    File::delete(File::glob(base_path('content/collections/pages/*.md')));
    File::delete(File::glob(base_path('content/collections/pages/*.*.md')));
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
