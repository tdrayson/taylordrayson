<?php

use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;

/*
 * Microformats are a machine contract: an IndieWeb reader or webmention
 * receiver parses this markup, and nothing on the page looks wrong when it
 * regresses.
 *
 * These run in a browser and parse the rendered DOM, because the markup lives
 * in Vue templates. That means they prove the markup is right once Vue has
 * run, not that it reaches the server-rendered HTML a parser would fetch — see
 * the SSR note in phpunit.xml.
 */

it('marks an article permalink up as an h-entry', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'title' => 'A titled piece',
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => PortableText::fromPlainText('The body of the piece.'),
    ]);

    $entry = microformatItem(microformatsOf($article->url()), 'h-entry');

    expect($entry['properties']['name'][0])->toBe('A titled piece')
        ->and($entry['properties']['published'][0])->toStartWith('2024-03-01')
        ->and($entry['properties']['url'][0])->toEndWith($article->url())
        ->and($entry['properties']['content'][0]['value'])->toContain('The body of the piece.')
        ->and($entry['properties']['author'][0]['properties']['name'][0])->toBe('Taylor Drayson');
});

// The distinction readers use to tell the two apart: a note is content with no
// name of its own, so the outline-only heading must not carry p-name.
it('leaves a note without a name, so it does not parse as an article', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-02 09:00:00',
        'content' => PortableText::fromPlainText('Just a thought.'),
    ]);

    $entry = microformatItem(microformatsOf($note->url()), 'h-entry');

    expect($entry['properties'])->not->toHaveKey('name')
        ->and($entry['properties']['content'][0]['value'])->toContain('Just a thought.');
});

it('wraps the timeline in an authored h-feed', function () {
    Article::factory()->create(['status' => 'published', 'occurred_at' => now()->subHour()]);

    $feed = microformatItem(microformatsOf('/', '.h-feed .h-entry'), 'h-feed');

    // The feed carries the author; consumers inherit it for each entry through
    // the authorship-discovery algorithm, so repeating it per entry is noise.
    expect($feed['properties']['author'][0]['properties']['name'][0])->toBe('Taylor Drayson')
        ->and($feed['children'])->not->toBeEmpty()
        ->and($feed['children'][0]['type'])->toContain('h-entry');
});
