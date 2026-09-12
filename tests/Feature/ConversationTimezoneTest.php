<?php

use App\Enums\CommentStatus;
use App\Models\Checkin;
use App\Models\Note;
use App\Presenters\Conversation;
use App\Support\EntryInstant;
use App\Support\PortableText;
use Carbon\Carbon;

/**
 * The conversation renders in its entry's timezone, never in home time
 * regardless of what the visitor's clock or the app's own UTC timezone says.
 */
it('shows a comment posted now at the real local clock time, not an hour early', function () {
    // 5:16pm British Summer Time, stored as the UTC instant it actually is.
    Carbon::setTestNow('2026-09-12 16:16:01');

    $note = Note::factory()->create();

    $note->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('Nice one.'),
        'status' => CommentStatus::Approved,
    ]);

    $responses = Conversation::for($note)->toArray()['responses'];

    expect($responses[0]['occurredAt']['label'])->toBe('Sat 12 Sep 2026, 5:16pm')
        ->and($responses[0]['occurredAt']['offset'])->toBe('+01:00');
});

it('renders a response to an entry recorded abroad in that entry\'s own timezone, not home time', function () {
    Carbon::setTestNow('2026-01-15 03:00:00');

    $checkin = Checkin::factory()->create([
        'occurred_at' => '2026-01-15 09:00:00',
        'timezone' => 'Australia/Sydney',
    ]);

    $checkin->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('G\'day.'),
        'status' => CommentStatus::Approved,
    ]);

    $responses = Conversation::for($checkin)->toArray()['responses'];

    // 03:00 UTC is 2pm in Sydney (AEDT, +11) and 3am at home (Europe/London):
    // rendering in home time would show the wrong hour entirely.
    expect($responses[0]['occurredAt']['offset'])->toBe('+11:00')
        ->and($responses[0]['occurredAt']['label'])->toBe('Thu 15 Jan 2026, 2:00pm');
});

it('never lets a response render as having happened before the entry it responds to', function () {
    $target = Note::factory()->create([
        'occurred_at' => '2026-06-01 09:00:00',
        'timezone' => 'Asia/Tokyo',
    ]);

    // One minute after the target's true instant, but recorded in a
    // different timezone entirely: comparing wall-clock digits instead of
    // real instants would get this backwards.
    $source = Note::factory()->create([
        'occurred_at' => '2026-06-01 01:01:00',
        'timezone' => 'Europe/London',
        'content' => PortableText::fromPlainText('See '.rtrim(config('app.url'), '/').$target->url()),
    ]);

    $targetInstant = EntryInstant::utc($target->occurred_at, $target->timezone());

    $responses = Conversation::for($target)->toArray()['responses'];

    expect($responses)->toHaveCount(1);

    $responseInstant = Carbon::parse($responses[0]['occurredAt']['iso']);

    expect($responseInstant->greaterThan($targetInstant))->toBeTrue();
});
