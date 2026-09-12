<?php

use App\Actions\Syndicated\PullSwarmResponses;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Checkin;
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

    $checkin = Checkin::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);

    app(PullSwarmResponses::class)($checkin, [
        'likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('24340263', 'Luke Allen')]]]],
        'comments' => ['count' => 0],
    ]);

    $response = $checkin->syndicatedResponses()->sole();

    expect($response->kind)->toBe(WebmentionKind::Like)
        ->and($response->author_name)->toBe('Luke Allen')
        ->and($response->source)->toBe(Source::Swarm->value);
});

// A check-in's share URL carries a signed token and is friends-only, so there is
// nothing worth linking a reader at.
it('leaves a swarm response unlinked', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $checkin = Checkin::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);

    app(PullSwarmResponses::class)($checkin, [
        'likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('24340263', 'Luke Allen')]]]],
    ]);

    expect($checkin->syndicatedResponses()->sole()->url)->toBeNull();
});

it('clears a like that has been taken back', function () {
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);

    $checkin = Checkin::factory()->create(['source' => Source::Swarm->value, 'source_id' => 'abc']);
    $pull = app(PullSwarmResponses::class);

    $pull($checkin, ['likes' => ['count' => 1, 'groups' => [['items' => [swarmLike('1', 'Luke Allen')]]]]]);
    $pull($checkin, ['likes' => ['count' => 0]]);

    expect($checkin->syndicatedResponses()->count())->toBe(0);
});
