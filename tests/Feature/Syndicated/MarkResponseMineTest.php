<?php

use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\SyndicatedResponse;
use App\Models\User;
use App\Queries\Hub\RecentResponses;
use App\Support\PortableText;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

function syndicatedReply(array $overrides = []): SyndicatedResponse
{
    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);

    return SyndicatedResponse::factory()->create([
        'target_type' => $activity->getMorphClass(),
        'target_id' => $activity->id,
        'kind' => WebmentionKind::Reply,
        'source_id' => '99',
        'author_name' => 'Taylor D.',
        'body' => PortableText::fromPlainText('Mine, actually.'),
        'occurred_at' => now()->subDay(),
        ...$overrides,
    ]);
}

it('marks a reply as mine and unmarks it again', function () {
    $reply = syndicatedReply();

    actingAs(User::factory()->create());

    patch("/responses/syndicated/{$reply->id}/mine", ['mine' => true]);
    expect($reply->fresh()->mine)->toBeTrue();

    patch("/responses/syndicated/{$reply->id}/mine", ['mine' => false]);
    expect($reply->fresh()->mine)->toBeFalse();
});

it('refuses a kudo, which every sync rebuilds', function () {
    $kudo = syndicatedReply(['kind' => WebmentionKind::Like, 'source_id' => null, 'body' => null]);

    actingAs(User::factory()->create());

    patch("/responses/syndicated/{$kudo->id}/mine", ['mine' => true])->assertForbidden();
    expect($kudo->fresh()->mine)->toBeFalse();
});

it('is not open to a visitor', function () {
    $reply = syndicatedReply();

    patch("/responses/syndicated/{$reply->id}/mine", ['mine' => true])->assertRedirect('/login');
    expect($reply->fresh()->mine)->toBeFalse();
});

it('leaves a reply of mine out of the hub', function () {
    syndicatedReply(['mine' => true]);

    expect(app(RecentResponses::class)(6))->toBeEmpty();
});
