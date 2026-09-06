<?php

use App\Enums\ReactionType;
use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Models\Reaction;

use function Pest\Laravel\postJson;

/**
 * Post a reaction as a given visitor. The identity is derived from the request
 * IP, so a different IP is a different person.
 */
function react(string $type, int $id, string $emoji = 'love', string $ip = '203.0.113.1')
{
    return postJson("/reactions/{$type}/{$id}", ['type' => $emoji], ['REMOTE_ADDR' => $ip]);
}

it('adds a reaction and toggles it off on a second click', function () {
    $note = Note::factory()->create();

    react('note', $note->id)
        ->assertSuccessful()
        ->assertJsonPath('on', true)
        ->assertJsonPath('reactions.1.key', 'love')
        ->assertJsonPath('reactions.1.count', 1)
        ->assertJsonPath('reactions.1.mine', true);

    react('note', $note->id)
        ->assertSuccessful()
        ->assertJsonPath('on', false)
        ->assertJsonPath('reactions.1.count', 0)
        ->assertJsonPath('reactions.1.mine', false);

    expect(Reaction::count())->toBe(0);
});

it('counts two visitors separately but one visitor once', function () {
    $note = Note::factory()->create();

    react('note', $note->id, ip: '203.0.113.1');
    react('note', $note->id, ip: '203.0.113.2');

    // A visitor holds one reaction, so picking another moves theirs rather
    // than adding a second: their love comes back off as the haha goes on.
    react('note', $note->id, 'haha', ip: '203.0.113.1');

    $counts = react('note', $note->id, 'wow', ip: '203.0.113.3')
        ->assertSuccessful()
        ->json('reactions');

    expect(collect($counts)->pluck('count', 'key')->all())
        ->toMatchArray(['love' => 1, 'haha' => 1, 'wow' => 1, 'celebrate' => 0, 'sad' => 0]);
});

it('moves a visitor to their new emoji, keeping when they first reacted', function () {
    $note = Note::factory()->create();

    react('note', $note->id, 'love');

    $before = Reaction::sole();
    $this->travel(5)->minutes();

    react('note', $note->id, 'haha')
        ->assertSuccessful()
        ->assertJsonPath('on', true);

    // The same row, moved. Reacting again is a change of mind about one
    // reaction, not a second one, so the time it was left does not reset.
    $after = Reaction::sole();

    expect($after->id)->toBe($before->id)
        ->and($after->type)->toBe(ReactionType::Haha)
        ->and($after->created_at->timestamp)->toBe($before->created_at->timestamp);
});

it('takes the reaction back when the same emoji is pressed again', function () {
    $note = Note::factory()->create();

    react('note', $note->id, 'love');
    react('note', $note->id, 'haha');

    react('note', $note->id, 'haha')
        ->assertSuccessful()
        ->assertJsonPath('on', false);

    expect(Reaction::count())->toBe(0);
});

it('ignores a forged X-Forwarded-For, so one machine cannot stuff the count', function () {
    $note = Note::factory()->create();

    foreach (['1.2.3.4', '5.6.7.8', '9.10.11.12'] as $claimed) {
        postJson("/reactions/note/{$note->id}", ['type' => 'love'], [
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_X_FORWARDED_FOR' => $claimed,
        ])->assertSuccessful();
    }

    // Three requests, one real connection: the first added a reaction and the
    // other two toggled it off and back on.
    expect(Reaction::count())->toBe(1);
});

it('returns every bucket even when nothing has been reacted to', function () {
    $note = Note::factory()->create();

    $buckets = react('note', $note->id)->json('reactions');

    // Like leads and is its own bucket: an incoming like-of, a kudo and a Swarm
    // like are plain approval, not a heart anybody picked.
    expect($buckets)->toHaveCount(6)
        ->and(collect($buckets)->pluck('key')->all())
        ->toBe(['like', 'love', 'celebrate', 'wow', 'haha', 'sad']);
});

it('accepts a published page, which is what makes a guestbook work', function () {
    $page = Page::factory()->create();

    react('page', $page->id)->assertSuccessful();
});

it('rejects a target that is not publicly visible', function () {
    $draftPage = Page::factory()->draft()->create();
    $draftArticle = Article::factory()->create(['published' => false]);

    react('page', $draftPage->id)->assertNotFound();
    react('article', $draftArticle->id)->assertNotFound();

    expect(Reaction::count())->toBe(0);
});

it('rejects a type that is not on the allowlist', function () {
    $note = Note::factory()->create();

    // Real things in the app, deliberately absent: a response to a listing has
    // nobody to notify and nothing to thread under.
    react('tag', $note->id)->assertNotFound();
    react('trip', $note->id)->assertNotFound();
    react('user', $note->id)->assertNotFound();
});

it('rejects an emoji outside the offered set', function () {
    $note = Note::factory()->create();

    react('note', $note->id, 'rocket')->assertStatus(422);

    expect(Reaction::count())->toBe(0);
});
