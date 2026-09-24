<?php

use App\Actions\Books\CreateBook;
use App\Actions\Flights\CreateFlight;
use App\Enums\EntryStatus;
use App\Enums\FieldType;
use App\Fields\AuthorableTypes;
use App\Fields\FieldRegistry;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Book;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(fn () => Carbon::setTestNow());

it('offers a status field on every authorable type', function () {
    foreach (AuthorableTypes::forPicker() as ['type' => $type]) {
        $model = AuthorableTypes::get($type)['model'];

        expect(collect(FieldRegistry::for(new $model))->firstWhere('name', 'status')?->type)->toBe(FieldType::Status, $type);
    }
});

it('saves a hand-written type as a draft, off the timeline and on /drafts', function (string $type, array $payload) {
    $this->actingAs(User::factory()->create());

    $this->post("/entries/{$type}", [...$payload, 'status' => 'draft'])->assertSessionHasNoErrors();

    $model = AuthorableTypes::get($type)['model']::query()->latest('id')->firstOrFail();

    expect($model->status)->toBe(EntryStatus::Draft)
        ->and($model->timelineEntry()->exists())->toBeFalse();

    $this->get('/drafts')->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->pluck('type')->contains($type)));
})->with([
    'note' => ['note', ['content' => 'Half a thought.', 'slug' => 'half-a-thought']],
    'event' => ['event', ['name' => 'Gig', 'tags' => ['Gig']]],
    'project' => ['project', ['title' => 'A project', 'description' => 'A summary', 'stage' => 'active']],
]);

it('lets the owner open a dated draft at its URL, and 404s it for a guest', function () {
    Note::factory()->create(['slug' => 'secret-plan', 'occurred_at' => '2026-06-15 09:00:00', 'status' => EntryStatus::Draft]);

    $this->get('/2026/06/15/secret-plan')->assertNotFound();

    $this->actingAs(User::factory()->create())->get('/2026/06/15/secret-plan')->assertOk();
});

it('leaves a draft undated, dates it when it leaves draft, and keeps the date and URL through a republish', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-20 10:00:00', 'UTC'));
    $this->actingAs(User::factory()->create());

    $this->post('/entries/article', ['title' => 'Slow cooking', 'status' => 'draft'])->assertSessionHasNoErrors();
    $article = Article::query()->sole();

    expect($article->occurred_at)->toBeNull()
        ->and($article->url())->toBe("/drafts/article/{$article->id}");

    $this->patch("/entries/article/{$article->id}", ['status' => 'published']);

    expect($article->fresh()->occurred_at->toDateString())->toBe('2026-06-20')
        ->and($article->fresh()->url())->toBe('/2026/06/20/slow-cooking');

    Carbon::setTestNow(Carbon::parse('2026-07-01 10:00:00', 'UTC'));
    $this->patch("/entries/article/{$article->id}", ['status' => 'draft']);

    expect($article->fresh()->occurred_at->toDateString())->toBe('2026-06-20')
        ->and($article->fresh()->timelineEntry()->exists())->toBeFalse();

    $this->patch("/entries/article/{$article->id}", ['status' => 'published']);

    expect($article->fresh()->url())->toBe('/2026/06/20/slow-cooking');
});

it('renders an undated draft of every draftable type for its owner, and sends a guest to log in', function (string $model) {
    fakeMapImages();

    $draft = $model::factory()->create(['status' => EntryStatus::Draft, 'occurred_at' => null]);

    $this->get($draft->url())->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get($draft->url())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Entry')->where('occurredAt', null)->where('dayUrl', null));
})->with([Note::class, Article::class, Project::class, Event::class, Book::class, Flight::class, Fuel::class, Appearance::class]);

it('creates a book being read as an undated draft with when it was started', function () {
    $book = app(CreateBook::class)([
        'title' => 'Dune',
        'status' => 'draft',
        'started_at' => '2026-05-01 20:00:00',
        'meta' => ['author' => 'Frank Herbert'],
    ]);

    expect($book->status)->toBe(EntryStatus::Draft)
        ->and($book->occurred_at)->toBeNull()
        ->and($book->started_at->toDateString())->toBe('2026-05-01');
});

it('creates a flight with no occurred_at as an undated draft', function () {
    Queue::fake();

    ['flight' => $flight] = app(CreateFlight::class)([
        'flight_number' => '2718',
        'airline_icao' => 'BAW',
        'origin_iata' => 'LGW',
        'destination_iata' => 'MAD',
        'status' => 'draft',
    ]);

    expect($flight->status)->toBe(EntryStatus::Draft)
        ->and($flight->occurred_at)->toBeNull();
});
