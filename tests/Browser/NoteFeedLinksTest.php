<?php

use App\Models\Note;
use App\Support\PortableText;

// The feed used to flatten a note to plain text, which dropped its marks: the
// link was still in the document and still rendered on the entry page, but the
// timeline printed the label with nothing behind it.
it('renders a note link as a real anchor in the timeline feed', function () {
    Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => PortableText::fromPlainText('Watch https://example.com/a-post today.'),
    ]);

    visit('/')->assertPresent('.e-content a[href="https://example.com/a-post"]');
});
