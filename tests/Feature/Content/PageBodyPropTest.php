<?php

// tests/Feature/Content/PageBodyPropTest.php

use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

it('passes bard body html to the Page component', function () {
    Entry::make()->collection('pages')->slug('colophon')
        ->data(['title' => 'Colophon', 'content' => '<p>Built with care</p>'])->save();
    $this->get('/colophon')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Page')
            ->where('bodyHtml', fn (string $html) => str_contains($html, 'Built with care')));
});
