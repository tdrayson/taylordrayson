<?php

use App\Models\Note;
use App\Models\User;
use App\Support\EntryInstant;
use Illuminate\Support\Carbon;

/**
 * `occurred_at` holds the clock as it read where the entry happened, but
 * app.timezone is UTC. The two agree only in winter, so an entry authored under
 * BST was stamped an hour early and occurred_utc then subtracted the offset a
 * second time.
 */
beforeEach(fn () => $this->actingAs(User::factory()->create()));

afterEach(fn () => Carbon::setTestNow());

it('stamps a new entry with the local clock, not the server\'s', function () {
    // 15:37 in London during BST is 14:37 UTC, which is what now() returns.
    Carbon::setTestNow(Carbon::parse('2026-08-28 14:37:23', 'UTC'));

    $this->post('/entries/note', ['content' => 'Filled up.', 'slug' => 'filled-up']);

    $note = Note::query()->latest('id')->firstOrFail();

    expect($note->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-28 15:37:23');
});

it('leaves the stamp alone in winter, when the two agree', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 14:37:23', 'UTC'));

    $this->post('/entries/note', ['content' => 'Cold out.', 'slug' => 'cold-out']);

    expect(Note::query()->latest('id')->firstOrFail()->occurred_at->format('Y-m-d H:i:s'))
        ->toBe('2026-01-15 14:37:23');
});

it('orders the entry by the instant it happened, not the server clock', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-28 14:37:23', 'UTC'));

    $this->post('/entries/note', ['content' => 'Ordered right.', 'slug' => 'ordered-right']);

    $note = Note::query()->latest('id')->firstOrFail();

    // The local reading converts back to the instant the server saw, rather
    // than an hour earlier again.
    expect(EntryInstant::utc($note->occurred_at, $note->timezone)->format('Y-m-d H:i:s'))
        ->toBe('2026-08-28 14:37:23');
});
