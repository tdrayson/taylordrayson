<?php

use App\Actions\Syndicated\PullSwarmResponses;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Place;
use Illuminate\Support\Facades\Http;

function swarmLike(string $id, string $name): array
{
    return [
        'id' => $id,
        'displayName' => $name,
        'photo' => ['prefix' => 'https://fastly.4sqi.net/img/user/', 'suffix' => "/{$id}_abc.jpg"],
    ];
}

it('stores a swarm like as a like, with the name the liker uses', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $place = Place::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);

    app(PullSwarmResponses::class)($place, [
        'likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('24340263', 'Luke Allen')]]]],
        'comments' => ['count' => 0],
    ]);

    $response = $place->syndicatedResponses()->sole();

    expect($response->kind)->toBe(WebmentionKind::Like)
        ->and($response->author_name)->toBe('Luke Allen')
        ->and($response->source)->toBe(Source::Swarm->value);
});

// A check-in's share URL carries a signed token and is friends-only, so there is
// nothing worth linking a reader at.
it('leaves a swarm response unlinked', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $place = Place::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);

    app(PullSwarmResponses::class)($place, [
        'likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('24340263', 'Luke Allen')]]]],
    ]);

    expect($place->syndicatedResponses()->sole()->url)->toBeNull();
});

it('clears a like that has been taken back', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $place = Place::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);
    $pull = app(PullSwarmResponses::class);

    $pull($place, ['likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('1', 'Luke Allen')]]]]]);
    $pull($place, ['likes' => ['count' => 0]]);

    expect($place->syndicatedResponses()->count())->toBe(0);
});

it('skips a comment with no id while storing a sibling that has one', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $place = Place::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);

    app(PullSwarmResponses::class)($place, [
        'likes' => ['count' => 0],
        'comments' => ['count' => 2, 'items' => [
            ['text' => 'This one has no id'],
            ['id' => 'c2', 'text' => 'This one has an id', 'user' => ['displayName' => 'Bob']],
        ]],
    ]);

    $response = $place->syndicatedResponses()->sole();

    expect($response->kind)->toBe(WebmentionKind::Reply)
        ->and($response->source_id)->toBe('c2')
        ->and($response->author_name)->toBe('Bob');
});

// Foursquare can report a positive count with no resolvable liker items, e.g.
// when the likers aren't visible to us. That is "we don't know", not "nobody
// liked this", and must not wipe the like we already hold.
it('leaves a stored like untouched when the count is positive but no likers resolve', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $place = Place::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);
    $pull = app(PullSwarmResponses::class);

    $pull($place, ['likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('1', 'Luke Allen')]]]]]);
    $pull($place, ['likes' => ['count' => 1, 'groups' => []]]);

    expect($place->syndicatedResponses()->sole()->author_name)->toBe('Luke Allen');
});

it('stores likers from multiple groups', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $place = Place::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);

    app(PullSwarmResponses::class)($place, [
        'likes' => ['count' => 2, 'groups' => [
            ['items' => [swarmLike('1', 'Alice')]],
            ['items' => [swarmLike('2', 'Bob')]],
        ]],
        'comments' => ['count' => 0],
    ]);

    expect($place->syndicatedResponses()->count())->toBe(2)
        ->and($place->syndicatedResponses()->pluck('author_name')->sort()->values()->all())->toBe(['Alice', 'Bob']);
});
