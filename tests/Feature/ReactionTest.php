<?php

use App\Enums\ReactionType;
use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Models\Reaction;
use App\Models\User;
use App\Queries\InteractionsForFeed;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

const REACTOR = '9b2f6c1e-4d3a-4f8b-9c7e-1a2b3c4d5e6f';

/**
 * Post a reaction as a given browser. The identity is the token the browser
 * holds, so a different token is a different person.
 */
function react(string $type, int $id, string $emoji = 'love', string $reactor = REACTOR, string $ip = '203.0.113.1')
{
    return postJson("/reactions/{$type}/{$id}", ['type' => $emoji, 'reactor' => $reactor], ['REMOTE_ADDR' => $ip]);
}

it('adds a reaction and toggles it off on a second click', function () {
    $note = Note::factory()->create();

    react('note', $note->id)
        ->assertSuccessful()
        ->assertJsonPath('on', true)
        ->assertJsonPath('reactions.1.key', 'love')
        ->assertJsonPath('reactions.1.count', 1);

    react('note', $note->id)
        ->assertSuccessful()
        ->assertJsonPath('on', false)
        ->assertJsonPath('reactions.1.count', 0);

    expect(Reaction::count())->toBe(0);
});

it('counts browsers separately, even behind one IP, but one browser once', function () {
    $note = Note::factory()->create();
    [$first, $second, $third] = [fake()->uuid(), fake()->uuid(), fake()->uuid()];

    react('note', $note->id, reactor: $first);
    react('note', $note->id, reactor: $second);

    // A visitor holds one reaction, so picking another moves theirs rather
    // than adding a second: their love comes back off as the haha goes on.
    react('note', $note->id, 'haha', reactor: $first);

    $counts = react('note', $note->id, 'wow', reactor: $third)
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

it('throttles an IP however many tokens it mints', function () {
    $note = Note::factory()->create();

    foreach (range(1, 30) as $ignored) {
        react('note', $note->id, reactor: fake()->uuid())->assertSuccessful();
    }

    react('note', $note->id, reactor: fake()->uuid())->assertTooManyRequests();

    // A forged X-Forwarded-For is not a new IP.
    postJson("/reactions/note/{$note->id}", ['type' => 'love', 'reactor' => fake()->uuid()], [
        'REMOTE_ADDR' => '203.0.113.1',
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
    ])->assertTooManyRequests();

    expect(Reaction::count())->toBe(30);
});

it('rejects a reaction without a token', function () {
    $note = Note::factory()->create();

    postJson("/reactions/note/{$note->id}", ['type' => 'love'])->assertStatus(422);

    expect(Reaction::count())->toBe(0);
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
    $draftArticle = Article::factory()->draft()->create();
    $privateArticle = Article::factory()->create(['status' => 'private', 'password' => 'secret']);

    react('page', $draftPage->id)->assertNotFound();
    react('article', $draftArticle->id)->assertNotFound();
    react('article', $privateArticle->id)->assertNotFound();

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

it('answers for a whole page of entries in a fixed number of queries', function () {
    $notes = Note::factory()->count(12)->create();

    foreach ($notes as $i => $note) {
        $note->reactions()->create(['type' => ReactionType::Love, 'identity_key' => hash('sha256', "q{$i}")]);
    }

    DB::enableQueryLog();
    $rows = app(InteractionsForFeed::class)(collect($notes), request());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Reactions, comments, mentions and syndicated responses. Four whatever
    // the page holds; the per-entry query would have been two dozen by now.
    expect($queries)->toBe(4)
        ->and($rows)->toHaveCount(12)
        ->and($rows['note:'.$notes[0]->id]['reactions'][1]['count'])->toBe(1);
});

it('refuses a reaction from the signed in author', function () {
    $note = Note::factory()->create();

    actingAs(User::factory()->create());

    react('note', $note->id)->assertForbidden();

    expect(Reaction::count())->toBe(0);
});
