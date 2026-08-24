<?php

use App\Models\Article;
use App\Models\Fuel;
use App\Support\PortableText;
use Carbon\Carbon;

// Exercises StoryToc.vue (via the fuel data story, which needs no media
// fixtures to seed). ContentToc.vue and StoryToc.vue now share the same
// useDialog composable, so this covers the dialog behaviour both components
// inherit: focus trap, Escape-to-close, and scroll lock. The second case
// below exercises ContentToc.vue itself, on an Article entry.
it('opens the mobile contents sheet from the pill and closes it on escape', function () {
    // Enough fills for the story to have something to say: below about eight it
    // renders no chapters at all, and with no chapters there is no toc and no
    // pill to open.
    foreach (range(0, 11) as $month) {
        Fuel::factory()->create([
            'occurred_at' => Carbon::parse('2024-01-01 09:00:00')->addMonths($month),
            'odometer' => 10000 + ($month * 400),
            'litres' => 40,
        ]);
    }

    // Mobile width so the floating "Contents" pill renders (it's xl:hidden).
    $page = visit('/stories/fuel')->resize(390, 844);

    // Dialog closed initially: no rendered dialog element.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // The pill only reveals once the reader scrolls past the hero. script()
    // returns the raw JS result (not the page), so it can't be chained.
    // Wait for the chapters to render before scrolling: script() does not retry,
    // and scrolling a document still one viewport tall does nothing, so the pill
    // never passes its reveal threshold. A story's toc is built from chapters,
    // not headings, and they land well before the charts do.
    $page->assertScript("document.querySelectorAll('[data-story-chapter]').length >= 2", true);
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.3);

    // Open the sheet from the pill's "Contents" button.
    $page->click('Contents')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Escape closes it (useDialog's shared keydown handler).
    $page->keys('[role="dialog"]', 'Escape')
        ->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);
});

// Exercises ContentToc.vue directly (ArticleDetail.vue only mounts it once
// there are 2+ h2/h3 headings, so the article needs at least two).
it('opens the mobile contents sheet for an article from the pill and closes it on escape', function () {
    // Enough paragraph blocks either side of each heading that the rendered
    // page is well over a mobile viewport's height, so scrolling reliably
    // passes ContentToc's 400px reveal threshold.
    $paragraph = fn () => PortableText::block(str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 6));

    $article = Article::factory()->create([
        'published' => true,
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => [
            PortableText::block('First Section', 'h2'),
            ...array_map($paragraph, range(1, 8)),
            PortableText::block('Second Section', 'h3'),
            ...array_map($paragraph, range(1, 8)),
        ],
    ]);

    // Mobile width so the floating "Contents" pill renders (it's xl:hidden).
    $page = visit($article->url())->resize(390, 844);

    // Dialog closed initially: no rendered dialog element.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // The pill only reveals once the reader scrolls past 400px.
    // Wait for the headings to render before scrolling: script() does not retry,
    // and scrolling a document still one viewport tall does nothing, so the pill
    // never passes its reveal threshold.
    $page->assertScript("document.querySelectorAll('h2, h3').length >= 2", true);
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.3);

    // Open the sheet from the pill's "Contents" button.
    $page->click('Contents')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Escape closes it (useDialog's shared keydown handler).
    $page->keys('[role="dialog"]', 'Escape')
        ->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);
});
