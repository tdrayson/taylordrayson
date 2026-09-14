<?php

use App\Enums\EntryStatus;
use App\Models\Book;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
    Storage::fake(config('media-library.disk_name'));
    Carbon::setTestNow('2026-09-14 12:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function kindleItem(array $overrides = []): array
{
    return [
        'cde_key' => '43FCE2B343F74D7EA0F93BC7AAB321E3',
        'type' => 'PDOC',
        'title' => 'Atomic Habits',
        'percent' => 9.71696000000000026,
        'last_open' => Carbon::parse('2026-09-14 08:00:00', 'UTC')->getTimestamp(),
        ...$overrides,
    ];
}

function syncKindle(array $items): TestResponse
{
    return test()->withToken('test-token')->postJson('/api/v1/kindle', [
        'device' => 'paperwhite',
        'reason' => 'appPaused',
        'synced_at' => Carbon::now()->getTimestamp(),
        'items' => $items,
    ]);
}

function kindleBook(array $attributes = []): Book
{
    return Book::factory()->create([
        'title' => 'Atomic Habits',
        'source' => 'kindle',
        'source_id' => '43FCE2B343F74D7EA0F93BC7AAB321E3',
        'status' => 'draft',
        'occurred_at' => null,
        'timezone' => 'Europe/London',
        'progress_percent' => 5.0,
        'progressed_at' => '2026-09-13 20:00:00',
        'meta' => [],
        ...$attributes,
    ]);
}

it('creates an undated draft for a book it has not seen', function () {
    syncKindle([kindleItem()])
        ->assertCreated()
        ->assertJsonPath('data.created', 1);

    $book = Book::sole();

    expect($book->status)->toBe(EntryStatus::Draft)
        ->and($book->source)->toBe('kindle')
        ->and($book->source_id)->toBe('43FCE2B343F74D7EA0F93BC7AAB321E3')
        ->and($book->progress_percent)->toBe(9.717)
        ->and($book->timezone)->toBe('Europe/London')
        ->and($book->progressed_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 09:00:00')
        ->and($book->started_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 09:00:00')
        ->and($book->occurred_at)->toBeNull()
        ->and($book->timelineEntry)->toBeNull();
});

it('counts a repeat of the same percent as unchanged', function () {
    syncKindle([kindleItem()])->assertCreated();

    syncKindle([kindleItem()])
        ->assertOk()
        ->assertJsonPath('data.unchanged', 1);
});

it('updates progress without overwriting a corrected title', function () {
    $book = kindleBook(['title' => 'Atomic Habits: Tiny Changes, Remarkable Results']);

    syncKindle([kindleItem(['percent' => 50.1234])])
        ->assertOk()
        ->assertJsonPath('data.updated', 1);

    expect($book->fresh())
        ->title->toBe('Atomic Habits: Tiny Changes, Remarkable Results')
        ->progress_percent->toBe(50.123);
});

it('ignores a snapshot older than the stored progress', function () {
    $book = kindleBook(['progress_percent' => 50.0, 'progressed_at' => '2026-09-14 08:00:00']);

    syncKindle([kindleItem(['percent' => 10, 'last_open' => Carbon::parse('2026-09-13 21:00:00', 'UTC')->getTimestamp()])])
        ->assertJsonPath('data.unchanged', 1);

    expect($book->fresh())
        ->progress_percent->toBe(50.0)
        ->progressed_at->format('Y-m-d H:i:s')->toBe('2026-09-14 08:00:00');
});

it('leaves a finished book alone', function () {
    $book = kindleBook(['status' => 'published', 'occurred_at' => '2026-09-01 21:00:00', 'progress_percent' => 100.0]);

    syncKindle([kindleItem(['percent' => 3])])->assertJsonPath('data.unchanged', 1);

    expect($book->fresh()->progress_percent)->toBe(100.0);
});

it('skips magazines and readings from the future', function () {
    syncKindle([
        kindleItem(['cde_key' => 'MAG1', 'type' => 'MAGZ']),
        kindleItem(['cde_key' => 'FUTURE', 'last_open' => Carbon::now()->addDays(3)->getTimestamp()]),
    ])
        ->assertOk()
        ->assertJsonPath('data.skipped', 2);

    expect(Book::count())->toBe(0);
});

it('publishes a complete book at 100%, dated when it was last opened', function () {
    $book = kindleBook(['meta' => ['author' => 'James Clear']]);
    $book->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    syncKindle([kindleItem(['percent' => 100])])->assertJsonPath('data.published', 1);

    expect($book->fresh())
        ->status->toBe(EntryStatus::Published)
        ->and($book->fresh()->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 09:00:00');
});

it('keeps an incomplete book as a draft at 100%', function () {
    $book = kindleBook();

    syncKindle([kindleItem(['percent' => 100])])->assertJsonPath('data.updated', 1);

    expect($book->fresh()->status)->toBe(EntryStatus::Draft);
});

it('never deletes a book missing from the snapshot', function () {
    kindleBook();

    syncKindle([kindleItem(['cde_key' => 'ANOTHER'])])->assertCreated();

    expect(Book::count())->toBe(2);
});

it('rejects a clock reset to 1970 and a missing token', function () {
    syncKindle([kindleItem(['last_open' => 0])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.last_open');

    // withToken() sets a default header that survives for the rest of the
    // test, so it must be cleared before asserting the unauthenticated case.
    $this->flushHeaders();
    $this->postJson('/api/v1/kindle', ['items' => [kindleItem()]])->assertUnauthorized();
});
