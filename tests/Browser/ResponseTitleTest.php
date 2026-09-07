<?php

use App\Enums\CommentStatus;
use App\Models\Note;
use App\Support\PortableText;

/**
 * The title line under a response byline, as it actually renders.
 *
 * Vue condenses a whitespace-only text node containing a newline to nothing, so
 * two elements on separate template lines render with no space between them.
 * The microformats tests cannot see this: the space sits outside p-name.
 */
function noteWithTitledMention(): Note
{
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-05 09:00:00',
        'content' => PortableText::fromPlainText('Something worth linking to.'),
    ]);

    $note->webmentions()->create([
        'source_url' => 'https://jan.example/posts/now',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'mention',
        'title' => 'Now, summer 2026',
        'author_name' => 'Jan',
        'author_url' => 'https://jan.example/',
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now(),
    ]);

    return $note;
}

it('puts a space between the preposition and the title', function () {
    visit(noteWithTitledMention()->url())
        ->assertPresent('.h-cite')
        ->assertScript(
            "document.querySelector('.h-cite .p-name').parentElement.textContent.replace(/\\s+/g, ' ').trim()",
            'in Now, summer 2026',
        );
});

it('does not let a response push the page sideways', function () {
    visit(noteWithTitledMention()->url())
        ->assertPresent('.h-cite')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);
});

it('sets the whole byline at one size, so the title is not the quietest thing on it', function () {
    // The name was text-meta and everything after it text-caption, which left
    // the phrase and the title smaller than the body they introduce.
    $sizes = "['.p-author', '.p-name', '.dt-published']"
        .".map((s) => getComputedStyle(document.querySelector('.h-cite ' + s)).fontSize)";

    visit(noteWithTitledMention()->url())
        ->assertPresent('.h-cite')
        ->assertScript("new Set({$sizes}).size", 1);
});
