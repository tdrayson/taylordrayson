<?php

use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\SyndicatedResponse;
use App\Models\User;
use App\Support\PortableText;

it('marks a syndicated reply as mine from the entry page', function () {
    $this->actingAs(User::factory()->create());

    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);

    $reply = SyndicatedResponse::factory()->create([
        'target_type' => $activity->getMorphClass(),
        'target_id' => $activity->id,
        'kind' => WebmentionKind::Reply,
        'source_id' => '99',
        'author_name' => 'Taylor D.',
        'body' => PortableText::fromPlainText('Mine, actually.'),
        'occurred_at' => now()->subDay(),
    ]);

    visit($activity->url())
        ->assertSee('Mark as mine')
        ->click('Mark as mine')
        ->assertSee('Not mine')
        ->screenshot(true, 'mine-marked');

    expect($reply->fresh()->mine)->toBeTrue();
});
