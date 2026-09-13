<?php

use App\Models\Note;
use App\Support\Links;
use App\Support\PortableText;
use Illuminate\Support\Facades\File;

// Stored up front for every test here, not just the one that asserts on it:
// rendering the feed at all triggers a favicon fetch for a host with none,
// which is a real HTTP call the suite refuses.
beforeEach(function (): void {
    File::ensureDirectoryExists(dirname(Links::faviconPath('example.com')));
    File::put(Links::faviconPath('example.com'), file_get_contents(base_path('tests/Fixtures/pixel.webp')));
});

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

// The favicon comes from the link data TimelineFeed provides, three components
// above NoteBody. Nothing else fails if that wiring breaks: the link still
// renders, it just loses its chip.
it('draws a favicon chip on a note link in the feed', function () {
    Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => PortableText::fromPlainText('Watch https://example.com/a-post today.'),
    ]);

    visit('/')->assertPresent('.e-content a[href="https://example.com/a-post"] img');
});

afterEach(fn () => File::delete(Links::faviconPath('example.com')));
