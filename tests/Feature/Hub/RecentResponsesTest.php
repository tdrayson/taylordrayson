<?php

use App\Enums\CommentStatus;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\Note;
use App\Models\SyndicatedResponse;
use App\Queries\Hub\RecentResponses;
use App\Support\PortableText;

it('collapses a day of kudos on one entry into one row', function () {
    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);

    foreach (['Clare A.', 'Justin M.', 'Brian D.'] as $who) {
        SyndicatedResponse::factory()->create([
            'target_type' => $activity->getMorphClass(),
            'target_id' => $activity->id,
            'kind' => WebmentionKind::Like,
            'author_name' => $who,
            'occurred_at' => now()->subDay(),
        ]);
    }

    $items = app(RecentResponses::class)(6);

    expect($items)->toHaveCount(1)
        ->and($items[0]->sentence)->toContain('Clare A.')
        ->and($items[0]->sentence)->toContain('Brian D.')
        ->and($items[0]->markable)->toBeFalse();
});

it('offers the mark on a row of kudos from one person', function () {
    $activity = Activity::factory()->create(['occurred_at' => now()->subDay()]);

    SyndicatedResponse::factory()->create([
        'target_type' => $activity->getMorphClass(),
        'target_id' => $activity->id,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Taylor D.',
        'occurred_at' => now()->subDay(),
    ]);

    expect(app(RecentResponses::class)(6)[0]->markable)->toBeTrue();
});

it('does not let a burst of same-day likes crowd out an older distinct response', function () {
    $activity = Activity::factory()->create(['occurred_at' => now()->subDays(2)]);
    $note = Note::factory()->create(['occurred_at' => now()->subDays(3)]);

    foreach (range(1, 7) as $i) {
        SyndicatedResponse::factory()->create([
            'target_type' => $activity->getMorphClass(),
            'target_id' => $activity->id,
            'kind' => WebmentionKind::Like,
            'author_name' => "Person {$i}",
            'occurred_at' => now()->subHours($i),
        ]);
    }

    SyndicatedResponse::factory()->create([
        'target_type' => $note->getMorphClass(),
        'target_id' => $note->id,
        'kind' => WebmentionKind::Reply,
        'author_name' => 'Old Reply',
        'body' => PortableText::fromPlainText('Ages ago.'),
        'occurred_at' => now()->subDays(1),
    ]);

    expect(app(RecentResponses::class)(6))->toHaveCount(2);
});

it('leaves a held comment out', function () {
    $note = Note::factory()->create(['occurred_at' => now()->subDay()]);

    $note->comments()->create([
        'author_name' => 'Jo Bloggs',
        'body' => PortableText::fromPlainText('Held back.'),
        'status' => CommentStatus::Pending,
    ]);

    expect(app(RecentResponses::class)(6))->toBeEmpty();
});

it('marks what arrived since the last visit', function () {
    $note = Note::factory()->create(['occurred_at' => now()->subDays(3)]);

    $old = $note->comments()->create([
        'author_name' => 'Old',
        'body' => PortableText::fromPlainText('Ages ago.'),
        'status' => CommentStatus::Approved,
    ]);
    $old->forceFill(['created_at' => now()->subDays(2)])->save();

    $note->comments()->create([
        'author_name' => 'New',
        'body' => PortableText::fromPlainText('Just now.'),
        'status' => CommentStatus::Approved,
    ]);

    $items = collect(app(RecentResponses::class)(6, now()->subDay()));

    $new = $items->first(fn ($item) => str_contains($item->sentence, 'New'));
    $old = $items->first(fn ($item) => str_contains($item->sentence, 'Old'));

    expect($new->isNew)->toBeTrue()->and($old->isNew)->toBeFalse();
});
