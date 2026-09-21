<?php

use App\Enums\EntryStatus;
use App\Models\Article;
use App\Models\Book;
use App\Queries\Hub\NeedsAttention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('is empty on a clean site', function () {
    expect(app(NeedsAttention::class)())->toBeEmpty();
});

it('raises a draft book that cannot publish yet', function () {
    Book::factory()->create([
        'title' => 'Untitled',
        'status' => EntryStatus::Draft,
        'meta' => ['author' => null],
    ]);

    $items = app(NeedsAttention::class)();

    expect($items)->toHaveCount(1)
        ->and($items[0]->title)->toContain('a book')
        ->and($items[0]->title)->toContain('an author');
});

it('raises a failed job', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateEntryMap']),
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    expect(app(NeedsAttention::class)())->toHaveCount(1);
});

it('forgets a draft nobody has touched in a month', function () {
    Article::factory()->create(['status' => EntryStatus::Draft, 'updated_at' => now()->subMonths(2)]);

    expect(app(NeedsAttention::class)())->toBeEmpty();
});

it('raises a draft edited recently', function () {
    Article::factory()->create(['status' => EntryStatus::Draft, 'updated_at' => now()->subDays(3)]);

    expect(app(NeedsAttention::class)())->toHaveCount(1);
});
