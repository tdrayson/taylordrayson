<?php

use App\Enums\CommentStatus;
use App\Enums\EntryStatus;
use App\Models\Book;
use App\Models\Note;
use App\Models\User;
use App\Support\PortableText;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('sends real entry counts to the page', function () {
    Note::factory()->create(['occurred_at' => now()->subHour()]);

    actingAs(User::factory()->create());

    get('/hq')->assertInertia(
        fn ($page) => $page->component('Hub')
            ->where('entries', fn ($entries) => collect($entries)->contains(
                fn ($entry) => $entry['label'] === 'Notes' && $entry['count'] === 1 && $entry['synced'] === false
            ))
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

it('marks responses new against the previous stamp, then advances it', function () {
    $this->freezeTime();

    $user = User::factory()->create(['hub_seen_at' => now()->subDay()]);
    actingAs($user);

    $note = Note::factory()->create(['occurred_at' => now()->subDays(2)]);
    $note->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('Hello.'),
        'status' => CommentStatus::Approved,
    ]);

    get('/hq')->assertInertia(fn ($page) => $page->where(
        'responses', fn ($responses) => collect($responses)->first()['isNew'] === true
    ));

    expect($user->fresh()->hub_seen_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('leaves the stamp unchanged when building the page throws', function () {
    $seenAt = now()->subDay();
    $user = User::factory()->create(['hub_seen_at' => $seenAt]);
    actingAs($user);

    Schema::drop('timeline_entries');

    get('/hq');

    expect($user->fresh()->hub_seen_at->toDateTimeString())->toBe($seenAt->toDateTimeString());
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

it('surfaces a non-zero exit from retrying failed jobs instead of looking successful', function () {
    $this->withoutExceptionHandling();

    actingAs(User::factory()->create());

    Artisan::shouldReceive('call')->once()->with('queue:retry', ['id' => ['all']])->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('Some queue driver error.');

    post('/hq/failed-jobs/retry');
})->throws(RuntimeException::class, 'Some queue driver error.');
