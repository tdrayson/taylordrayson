<?php

use App\Enums\CommentStatus;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Note;
use App\Models\Place;
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

    $place = Place::factory()->create([
        'occurred_at' => '2026-01-15 09:00:00',
        'timezone' => 'Australia/Sydney',
    ]);

    $place->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('G\'day.'),
        'status' => CommentStatus::Approved,
    ]);

    $responses = Conversation::for($place)->toArray()['responses'];

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

/**
 * Aaron Parecki's convention: a response with its own known timezone renders
 * at the time its author saw, not converted into the entry's clock.
 */
it('renders a comment in the commenter\'s own timezone, not the entry\'s', function () {
    // 4:16pm UTC: 5:16pm in London (BST), 12:16pm in New York (EDT).
    Carbon::setTestNow('2026-09-12 16:16:00');

    $note = Note::factory()->create(['timezone' => 'Europe/London']);

    $note->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('Hello from New York.'),
        'status' => CommentStatus::Approved,
        'timezone' => 'America/New_York',
    ]);

    $responses = Conversation::for($note)->toArray()['responses'];

    expect($responses[0]['occurredAt']['offset'])->toBe('-04:00')
        ->and($responses[0]['occurredAt']['label'])->toBe('Sat 12 Sep 2026, 12:16pm');
});

it('falls back to the entry\'s timezone when a comment carries none at all', function () {
    Carbon::setTestNow('2026-09-12 16:16:00');

    $note = Note::factory()->create(['timezone' => 'Europe/London']);

    $note->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('No timezone captured.'),
        'status' => CommentStatus::Approved,
        'timezone' => null,
    ]);

    $responses = Conversation::for($note)->toArray()['responses'];

    expect($responses[0]['occurredAt']['offset'])->toBe('+01:00');
});

it('renders a webmention at the offset its dt-published carried', function () {
    $note = Note::factory()->create(['timezone' => 'Europe/London']);

    $note->webmentions()->create([
        'source_url' => 'https://jo.example/post',
        'target_url' => rtrim(config('app.url'), '/').$note->url(),
        'kind' => 'mention',
        'author_name' => 'Jo',
        'status' => CommentStatus::Approved,
        'published_at' => '2025-11-11 12:54:00',
        'timezone' => '+05:30',
    ]);

    $responses = Conversation::for($note)->toArray()['responses'];

    expect($responses[0]['occurredAt']['offset'])->toBe('+05:30');
});

it('still renders a Strava kudo in the entry\'s timezone, since no author timezone is available', function () {
    Carbon::setTestNow('2026-01-15 03:00:00');

    $place = Place::factory()->create([
        'occurred_at' => '2026-01-15 09:00:00',
        'timezone' => 'Australia/Sydney',
    ]);

    $place->syndicatedResponses()->create([
        'source' => Source::Strava->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Justin M.',
        'occurred_at' => now(),
    ]);

    $responses = Conversation::for($place)->toArray()['responses'];

    expect($responses[0]['occurredAt']['offset'])->toBe('+11:00');
});
