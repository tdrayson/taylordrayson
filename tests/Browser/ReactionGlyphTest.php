<?php

use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Models\Note;
use App\Support\PortableText;
use App\Support\VisitorIdentity;
use Illuminate\Http\Request;

/**
 * The reaction discs, as they actually paint.
 *
 * Two properties neither a unit test nor the microformats tests can see: that
 * every glyph name resolves to a real icon, and that the glyph stays legible on
 * the disc behind it.
 */
function noteWithEveryReaction(): Note
{
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-06 09:00:00',
        'content' => PortableText::fromPlainText('Something worth reacting to.'),
    ]);

    foreach (ReactionType::cases() as $index => $type) {
        $note->reactions()->create([
            'type' => $type,
            'identity_key' => hash('sha256', 'glyph-test-'.$index),
        ]);
    }

    return $note;
}

it('resolves an icon for every reaction, so no disc paints empty', function () {
    // Icon.vue renders nothing at all when a name is missing from the registry,
    // and a production build prints no warning about it.
    visit(noteWithEveryReaction()->url())
        ->assertPresent('.reaction-pip')
        ->assertScript('document.querySelectorAll(".reaction-pip svg").length', count(ReactionType::cases()));
});

it('keeps every disc dark enough to carry its glyph', function () {
    // Wow and haha were yellow, where white read at 2.10:1 and 2.03:1 against
    // the 3:1 WCAG asks of a graphical object. Measured on the painted pixels
    // rather than the tokens, so a later theme edit cannot quietly undo it.
    $failing = <<<'JS'
    (() => {
        const channel = (v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
        const luminance = (s) => {
            const [r, g, b] = s.match(/[\d.]+/g).slice(0, 3).map((n) => channel(n / 255));
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        return [...document.querySelectorAll('.reaction-pip')].filter((el) => {
            const style = getComputedStyle(el);
            const a = luminance(style.backgroundColor);
            const b = luminance(style.color);
            return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) < 3;
        }).length;
    })()
    JS;

    visit(noteWithEveryReaction()->url())
        ->assertPresent('.reaction-pip')
        ->assertScript($failing, 0);
});

it('counts reposts and bookmarks only once somebody has done one', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-09 09:00:00',
        'content' => PortableText::fromPlainText('Something worth keeping.'),
    ]);

    // A permanent pair of zeroes would be furniture on every entry. Reactions
    // and the written-response count keep showing at zero; these do not.
    $labelled = "[...document.querySelectorAll('[aria-label]')]"
        .".map((el) => el.getAttribute('aria-label')).filter((l) => /repost|bookmark/.test(l))";

    // Waited for: script() reads the DOM the moment it is called, so asserting
    // without one races Vue and passes against an empty shell.
    visit($note->url())
        ->assertPresent('[data-testid="reaction-bar"]')
        ->assertScript("{$labelled}.length", 0);

    foreach (['repost', 'bookmark'] as $index => $kind) {
        $note->webmentions()->create([
            'source_url' => "https://jan.example/{$kind}",
            'target_url' => config('app.url').$note->url(),
            'kind' => $kind,
            'author_name' => 'Jan',
            'status' => CommentStatus::Approved,
            'verified_at' => now(),
            'published_at' => now()->subMinutes($index),
        ]);
    }

    visit($note->url())
        ->assertPresent('[data-testid="reaction-bar"]')
        ->assertScript("{$labelled}.sort().join('|')", '1 bookmark|1 repost');
});

it('sets every number on the row at one size, so they sit on one line', function () {
    // The per-reaction counts were text-caption while the reply, repost and
    // bookmark counts beside them were text-body, so no two lined up.
    $note = noteWithEveryReaction();

    $note->webmentions()->create([
        'source_url' => 'https://jan.example/repost',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'repost',
        'author_name' => 'Jan',
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now(),
    ]);

    $sizes = "new Set([...document.querySelectorAll('[data-testid=\"reaction-bar\"] .tnum')]"
        .'.map((el) => getComputedStyle(el).fontSize)).size';

    visit($note->url())
        ->assertPresent('[data-testid="reaction-bar"] .tnum')
        ->assertScript($sizes, 1);
});

it('keeps every count the same colour, including the one you reacted with', function () {
    // The trigger used to go accent blue once you had reacted, which fought
    // whatever colour the disc beside it happened to be. Tinting it to the
    // reaction instead is not an option: four of the six fail text contrast.
    $note = noteWithEveryReaction();

    $note->webmentions()->create([
        'source_url' => 'https://jan.example/repost',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'repost',
        'author_name' => 'Jan',
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now(),
    ]);

    $colours = "new Set([...document.querySelectorAll('[data-testid=\"reaction-bar\"] .tnum')]"
        .'.map((el) => getComputedStyle(el).color)).size';

    // Seeded rather than clicked: the accent only ever applied once you were in
    // the count, so asserting on a page where nobody has reacted proves nothing,
    // and clicking the control leaves it hovered, which is a colour of its own.
    foreach (['127.0.0.1', '::1'] as $ip) {
        $note->reactions()->create([
            'type' => ReactionType::Love,
            'identity_key' => VisitorIdentity::onTarget(
                Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip]),
                $note,
            ),
        ]);
    }

    visit($note->url())
        ->assertPresent('[data-testid="reaction-bar"] .tnum')
        ->assertScript("document.querySelector('[data-testid=\"reaction-bar\"] button').getAttribute('aria-pressed')", 'true')
        ->assertScript($colours, 1);
});

it('bolds the total when you are one of the people in it', function () {
    $note = noteWithEveryReaction();

    // Nobody has reacted from this browser, so the total reads like the rest.
    $weight = "getComputedStyle(document.querySelector('[data-testid=\"reaction-bar\"] button .tnum')).fontWeight";

    $page = visit($note->url())->assertPresent('[data-testid="reaction-bar"]');
    $page->assertScript($weight, '500');

    // Reacting is the only thing that changes, since the count stays the same
    // colour and size as the ones beside it.
    $page->click('[aria-label="React to this"]')
        ->assertScript($weight, '700');
});
