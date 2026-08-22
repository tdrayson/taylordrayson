<?php

use App\Models\Article;
use App\Support\Links;
use App\Support\PortableText;
use Illuminate\Support\Facades\File;

// The page resolves favicons and previews; BlockContent, three components down,
// is the only thing that reads them. Nothing else fails if that wiring breaks:
// the prose still renders, the links just lose their marks.
it('draws favicon chips on external links in an article', function () {
    File::ensureDirectoryExists(dirname(Links::faviconPath('example.com')));
    File::put(Links::faviconPath('example.com'), file_get_contents(base_path('tests/Fixtures/pixel.webp')));

    $article = Article::factory()->create([
        'published' => true,
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => PortableText::fromPlainText('Read https://example.com/a-post today.'),
    ]);

    visit($article->url())
        ->assertPresent('.block-content a[href="https://example.com/a-post"] img');
});

// The favicon lives on disk, not in the database, so RefreshDatabase does not
// clear it. Left behind it makes SmartLinksTest see the host as already
// resolved and skip the queue assertion.
afterEach(fn () => File::delete(Links::faviconPath('example.com')));
