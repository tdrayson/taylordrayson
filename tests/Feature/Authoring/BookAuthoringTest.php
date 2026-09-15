<?php

use App\Enums\EntryStatus;
use App\Fields\BookFields;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
    Http::fake(['assets.example.com/*' => Http::response(fakeJpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
    Carbon::setTestNow('2026-09-14 12:00:00');
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

function kindleDraft(array $attributes = []): Book
{
    return Book::factory()->create([
        'title' => 'Atomic Habits',
        'source' => 'kindle',
        'source_id' => 'CDE1',
        'status' => 'draft',
        'occurred_at' => null,
        'progress_percent' => 40.0,
        'progressed_at' => '2026-09-13 21:30:00',
        'meta' => [],
        ...$attributes,
    ]);
}

it('refuses to publish a book without an author and a cover', function () {
    $book = kindleDraft();

    $this->patch("/entries/book/{$book->id}", ['title' => 'Atomic Habits', 'status' => 'published'])
        ->assertSessionHasErrors(['status' => 'Add an author and a cover before publishing.']);

    expect($book->fresh()->status)->toBe(EntryStatus::Draft);
});

it('publishes once matched, dated at the last progress update', function () {
    $book = kindleDraft();

    $this->patch("/entries/book/{$book->id}", [
        'title' => 'Atomic Habits',
        'meta' => ['author' => 'James Clear'],
        'cover' => ['url:https://assets.example.com/atomic.jpeg'],
        'tags' => ['Self-Help'],
        'status' => 'published',
    ])->assertSessionHasNoErrors();

    $fresh = $book->fresh();

    expect($fresh->status)->toBe(EntryStatus::Published)
        ->and($fresh->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-09-13 21:30:00')
        ->and($fresh->hasMedia('cover'))->toBeTrue()
        ->and($fresh->tagNames())->toBe(['Self-Help']);
});

it('publishes a matched Kindle book already at 100% on save', function () {
    $book = kindleDraft(['progress_percent' => 100.0]);

    $this->patch("/entries/book/{$book->id}", [
        'meta' => ['author' => 'James Clear'],
        'cover' => ['url:https://assets.example.com/atomic.jpeg'],
        'status' => 'draft',
    ])->assertSessionHasNoErrors();

    expect($book->fresh()->status)->toBe(EntryStatus::Published);
});

it('ignores progress posted for a Kindle book', function () {
    $book = kindleDraft();

    $this->patch("/entries/book/{$book->id}", ['progress_percent' => 99, 'current_page' => 10, 'pages' => 20, 'source_id' => 'HACKED'])
        ->assertSessionHasNoErrors();

    expect($book->fresh())
        ->progress_percent->toBe(40.0)
        ->current_page->toBeNull()
        ->source_id->toBe('CDE1');
});

it('turns a page into percent for a manual book', function () {
    $book = Book::factory()->create(['source' => 'manual', 'status' => 'draft', 'occurred_at' => null, 'timezone' => 'Europe/London']);

    $this->patch("/entries/book/{$book->id}", ['current_page' => 122, 'pages' => 288])->assertSessionHasNoErrors();

    expect($book->fresh())
        ->progress_percent->toBe(42.361)
        ->and($book->fresh()->progressed_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 13:00:00');
});

it('clears progress when the page is cleared', function () {
    $book = Book::factory()->create([
        'source' => 'manual',
        'status' => 'draft',
        'occurred_at' => null,
        'current_page' => 122,
        'pages' => 288,
        'progress_percent' => 42.361,
        'progressed_at' => '2026-09-14 09:00:00',
    ]);

    $this->patch("/entries/book/{$book->id}", ['current_page' => null])->assertSessionHasNoErrors();

    expect($book->fresh())
        ->current_page->toBeNull()
        ->progress_percent->toBeNull()
        ->progressed_at->toBeNull();
});

it('asks for the page count before tracking by page', function () {
    $book = Book::factory()->create(['source' => 'manual', 'status' => 'draft', 'occurred_at' => null]);

    $this->patch("/entries/book/{$book->id}", ['current_page' => 122])
        ->assertSessionHasErrors(['pages' => 'Add the page count to track by page.']);
});

it('still saves an older published book that has no cover', function () {
    $book = Book::factory()->create(['source' => 'manual', 'status' => 'published']);

    $this->patch("/entries/book/{$book->id}", ['rating' => 8])->assertSessionHasNoErrors();

    expect($book->fresh()->rating)->toBe(8);
});

it('rejects a rating outside 1 to 10', function (int|float $rating) {
    $book = Book::factory()->create(['source' => 'manual', 'status' => 'published']);

    $this->patch("/entries/book/{$book->id}", ['rating' => $rating])
        ->assertSessionHasErrors(['rating']);
})->with([0, 11, 7.5]);

it('saves a rating at the edges of the scale', function (int $rating) {
    $book = Book::factory()->create(['source' => 'manual', 'status' => 'published']);

    $this->patch("/entries/book/{$book->id}", ['rating' => $rating])
        ->assertSessionHasNoErrors();

    expect($book->fresh()->rating)->toBe($rating);
})->with([1, 10]);

it('allows a blank rating', function () {
    $book = Book::factory()->create(['source' => 'manual', 'status' => 'published', 'rating' => 8]);

    $this->patch("/entries/book/{$book->id}", ['rating' => ''])
        ->assertSessionHasNoErrors();

    expect($book->fresh()->rating)->toBeNull();
});

it('offers progress and the Kindle id read-only on a Kindle book', function () {
    $fields = collect(BookFields::fields(kindleDraft()))->keyBy('name');

    expect($fields['percent_read']->readOnly)->toBeTrue()
        ->and($fields['source_id']->readOnly)->toBeTrue()
        ->and($fields->has('current_page'))->toBeFalse()
        ->and(collect(BookFields::fields())->pluck('name'))->toContain('current_page', 'pages')->not->toContain('source_id');
});

it('lists what each draft book still needs', function () {
    kindleDraft(['progress_percent' => 42.9]);

    $this->get('/drafts')->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.rows.0.detail', '42%, needs author and cover')
    );
});

it('floors reading progress for display and carries it on the entry payload', function () {
    $book = kindleDraft(['progress_percent' => 42.9]);

    expect($book->percent_read)->toBe(42)
        ->and($book->toArray()['percent_read'])->toBe(42);
});

it('lets a published book go back to draft even at 100 percent', function () {
    $book = Book::factory()->create([
        'source' => 'manual',
        'status' => 'published',
        'progress_percent' => 100.0,
        'meta' => ['author' => 'James Clear'],
    ]);
    $book->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    $this->patch("/entries/book/{$book->id}", ['status' => 'draft'])->assertSessionHasNoErrors();

    expect($book->fresh()->status)->toBe(EntryStatus::Draft);
});

it('refuses to create a published book without an author and a cover', function () {
    $this->post('/entries/book', ['title' => 'Atomic Habits', 'status' => 'published'])
        ->assertSessionHasErrors(['status' => 'Add an author and a cover before publishing.']);

    expect(Book::count())->toBe(0);
});
