<?php

use App\Datasets\Datasets;
use App\Jobs\RunSyncCommand;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(fn () => Queue::fake());

it('queues the command the schedule runs for that type', function (string $dataset, string $command) {
    actingAs(User::factory()->create());

    post("/hq/sync/{$dataset}")->assertRedirect();

    Queue::assertPushed(RunSyncCommand::class, fn (RunSyncCommand $job): bool => $job->command === $command);
})->with([
    ['activity', 'strava:sync --days=2 --refresh'],
    ['film', 'trakt:sync --days=1 --skip-ratings'],
    ['tv-episode', 'trakt:sync --days=1 --skip-ratings'],
    ['food', 'rovi:sync-food'],
    ['place', 'foursquare:sync'],
    ['this-week-with', 'this-week-with:sync'],
]);

it('404s a type with nothing to pull', function (string $dataset) {
    actingAs(User::factory()->create());

    post("/hq/sync/{$dataset}")->assertNotFound();

    Queue::assertNothingPushed();
})->with(['sleep', 'book', 'note', 'unicorn']);

it('keeps guests out', function () {
    post('/hq/sync/activity')->assertRedirect('/login');

    Queue::assertNothingPushed();
});

it('finds every sync command on the schedule, where its overlap lock lives', function () {
    app(Kernel::class)->bootstrap();

    $locked = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => $event->withoutOverlapping)
        ->map(fn (Event $event): string => $event->command ?? '');

    collect(Datasets::all())
        ->map->syncCommand()
        ->filter()
        ->each(fn (string $command) => expect($locked->contains(fn (string $scheduled): bool => str_ends_with($scheduled, ' '.$command)))->toBeTrue());
});
