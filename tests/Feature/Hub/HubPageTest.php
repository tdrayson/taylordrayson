<?php

use App\Enums\EntryStatus;
use App\Models\Book;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('sends real entry counts to the page', function () {
    Note::factory()->create(['occurred_at' => now()->subHour()]);

    actingAs(User::factory()->create());

    get('/hq')->assertInertia(
        fn ($page) => $page->component('Hub')
            ->where('entries', fn ($entries) => (function () use ($entries) {
                $notes = collect($entries)->first(
                    fn ($entry) => $entry['label'] === 'Notes'
                );

                return $notes !== null
                    && $notes['count'] === 1
                    && $notes['synced'] === false;
            })())
    );
});

it('is not reachable signed out', function () {
    get('/hq')->assertRedirect('/login');
});

it('shares the waiting count with every page', function () {
    actingAs(User::factory()->create());

    get('/')->assertInertia(fn ($page) => $page->where('hubWaiting', 0));
});

it('caches the waiting count rather than recomputing it every request', function () {
    actingAs(User::factory()->create());

    Book::factory()->create(['title' => 'Untitled', 'status' => EntryStatus::Draft, 'meta' => ['author' => null]]);

    get('/')->assertInertia(fn ($page) => $page->where('hubWaiting', 1));

    Book::query()->delete();

    // Still 1: the count came from cache, not a fresh NeedsAttention run.
    get('/')->assertInertia(fn ($page) => $page->where('hubWaiting', 1));
});

it('does not carry a cached waiting count over from another test', function () {
    actingAs(User::factory()->create());

    get('/')->assertInertia(fn ($page) => $page->where('hubWaiting', 0));
});

it('retries the failed jobs and clears the row', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateEntryMap']),
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    actingAs(User::factory()->create());

    post('/hq/failed-jobs/retry')->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});
