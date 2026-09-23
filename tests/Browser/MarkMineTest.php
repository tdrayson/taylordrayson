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
        ->assertPresent('[aria-label="Mark as mine"]')
        ->click('[aria-label="Mark as mine"]')
        ->assertPresent('[aria-label="Not mine"]')
        ->screenshot(true, 'mine-marked');

    expect($reply->fresh()->mine)->toBeTrue();
});

it('offers the same mark on a hub response row', function () {
    $this->actingAs(User::factory()->create());

    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);

    SyndicatedResponse::factory()->create([
        'target_type' => $activity->getMorphClass(),
        'target_id' => $activity->id,
        'kind' => WebmentionKind::Reply,
        'source_id' => '98',
        'author_name' => 'Taylor D.',
        'body' => PortableText::fromPlainText('Also mine.'),
        'occurred_at' => now()->subDay(),
    ]);

    visit('/hq')->assertPresent('[data-response-row] [aria-label="Mark as mine"]');
});
