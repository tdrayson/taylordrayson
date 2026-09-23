<?php

use App\Jobs\RunSyncCommand;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a sync from its HQ row and says so on that row', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    visit('/hq')
        ->assertNotPresent('li:has-text("Sleep") button')
        ->click('[aria-label="Sync Activities now"]')
        ->assertPresent('[aria-label="Activities sync queued"]')
        ->assertNoJavaScriptErrors()
        ->screenshot(true, 'sync-now');

    Queue::assertPushed(RunSyncCommand::class, fn (RunSyncCommand $job): bool => $job->command === 'strava:sync --days=2 --refresh');
});

it('keeps each row ticked when a second sync is queued', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    visit('/hq')
        ->click('[aria-label="Sync Activities now"]')
        ->assertPresent('[aria-label="Activities sync queued"]')
        ->click('[aria-label="Sync Films now"]')
        ->assertPresent('[aria-label="Films sync queued"]')
        ->assertPresent('[aria-label="Activities sync queued"]');
});
