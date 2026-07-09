# API v1 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the standardised `/api/v1` write/read API and shared action layer, with notes and flights as the two exemplar resource verticals, and canonical units (seconds, metres) across the schema.

**Architecture:** The site becomes an API-first source of truth. All writes go through single-purpose action classes (`app/Actions/{Type}/`), which API controllers (and later MCP tools and sync commands) call. Auth is a single static bearer token from `.env` (`API_TOKEN`), verified with a constant-time compare; no Sanctum. Distance columns are unified to bare `distance` in integer metres (mirroring bare `duration` in seconds); display layers convert. API inputs accept flexible unit strings ("2h 56m", "774 miles") normalised by a `Units` parser.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, existing Eloquent models (`Note`, `Flight`, `Activity`), Inertia v3/Vue 3.

**This is Plan 1 of 4.** Follow-ups (separate plans, do not build here): Plan 2 MCP server on the same actions; Plan 3 articles/pages rich-text format + compose UI; Plan 4 remaining resource types + moving sync commands onto actions.

## Global Constraints

- PHP 8.4, Laravel 13, Pest 4. Feature tests, not unit tests, unless stated.
- Auth: single bearer token from `config('services.api.token')` (env `API_TOKEN`). The API **fails closed**: if the config value is empty, every request is 401.
- Route naming: `/api/v1/{slug}` where slug is the kebab-case plural from `app/Timeline/TypeRegistry.php` (`notes`, `flights`).
- Response shapes: single = `{ "data": {...} }` via API Resources; lists = Laravel resource pagination (`data`, `links`, `meta`); errors = Laravel JSON defaults (`{ "message": ..., "errors": {...} }` for 422).
- Canonical units, stored and returned by the API: `duration` = integer seconds, `distance` = integer metres, `occurred_at` = local wall-clock. Display layers convert (km for activities, miles for flights). API requests may pass unit strings; they are normalised before validation.
- Always explicit return types and parameter type hints. PHPDoc over inline comments. Curly braces always.
- Form Request classes for validation, never inline `$request->validate()`.
- No `env()` outside config files.
- Run `vendor/bin/pint --dirty --format agent` before every commit.
- Do NOT delete existing tests. Update them only where the notes plaintext conversion or distance canonicalisation requires it.
- Commit messages: conventional commits (`feat:`, `fix:`, `test:`, `refactor:`), no attribution footer.

---

### Task 1: API token middleware + /api/v1 route group

**Files:**
- Modify: `config/services.php` (add `api.token`)
- Modify: `.env.example` (add `API_TOKEN=`)
- Create: `app/Http/Middleware/AuthenticateApiToken.php`
- Modify: `bootstrap/app.php` (register `api.token` middleware alias)
- Modify: `routes/api.php` (v1 group + ping)
- Test: `tests/Feature/Api/ApiContractTest.php`

**Interfaces:**
- Produces: middleware alias `api.token`; a `Route::prefix('v1')->middleware('api.token')->name('api.v1.')` group in `routes/api.php` that later tasks register resources inside. Test convention all later tasks reuse: `config()->set('services.api.token', 'test-token')` in `beforeEach`, then `$this->withToken('test-token')->...`.

- [ ] **Step 1: Write the failing contract test**

Create `tests/Feature/Api/ApiContractTest.php`:

```php
<?php

it('rejects requests without a token', function () {
    config()->set('services.api.token', 'test-token');

    $this->getJson('/api/v1/ping')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('rejects requests with a wrong token', function () {
    config()->set('services.api.token', 'test-token');

    $this->withToken('wrong')->getJson('/api/v1/ping')->assertUnauthorized();
});

it('fails closed when no token is configured', function () {
    config()->set('services.api.token', null);

    $this->withToken('anything')->getJson('/api/v1/ping')->assertUnauthorized();
});

it('responds to ping with a valid token', function () {
    config()->set('services.api.token', 'test-token');

    $this->withToken('test-token')->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJson(['data' => ['ok' => true]]);
});

it('returns JSON 404 for unknown v1 routes when authenticated', function () {
    config()->set('services.api.token', 'test-token');

    $this->withToken('test-token')->getJson('/api/v1/does-not-exist')->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ApiContractTest`
Expected: FAIL (routes missing, middleware missing).

- [ ] **Step 3: Add config + env example**

In `config/services.php`, add to the returned array:

```php
'api' => [
    'token' => env('API_TOKEN'),
],
```

In `.env.example`, add:

```
API_TOKEN=
```

(For real use, generate with `openssl rand -hex 32` and set in `.env` / production env.)

- [ ] **Step 4: Implement the middleware**

Create `app/Http/Middleware/AuthenticateApiToken.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Authenticate against the single static API token. Fails closed:
     * an unconfigured token rejects every request rather than allowing all.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.api.token');
        $provided = $request->bearerToken();

        $valid = is_string($expected)
            && $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);

        if (! $valid) {
            return response()->json(['message' => 'Invalid or missing API token.'], 401);
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Register the alias**

Read `bootstrap/app.php` first. Inside the existing `->withMiddleware(function (Middleware $middleware) { ... })` callback, add (merging into an existing `alias([...])` call if one exists):

```php
$middleware->alias([
    'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
]);
```

- [ ] **Step 6: Add the v1 group**

In `routes/api.php`, below the existing health routes (leave those untouched):

```php
Route::prefix('v1')->middleware('api.token')->name('api.v1.')->group(function () {
    Route::get('/ping', fn () => response()->json(['data' => ['ok' => true]]))->name('ping');
});
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=ApiContractTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Full suite + commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: env API token auth + /api/v1 route group"
```

---

### Task 2: Convert notes to plaintext

**Files:**
- Modify: `app/Models/Note.php` (drop `array` cast + EditorJs usage)
- Modify: `database/factories/NoteFactory.php`
- Modify: `resources/js/Components/Entry/NoteDetail.vue` (render plaintext, not BlockContent) — confirm exact path with `find resources/js -name NoteDetail.vue`
- Modify: any tests seeding Editor.js note content (discover in Step 1)
- Leave alone: `app/Support/EditorJs.php` (Articles keep block content until Plan 3)

**Interfaces:**
- Produces: `Note::$content` is a plain string; `Note::card()['title']` is `Str::limit($this->content, 80)`. Task 3 relies on `content` being a string.

- [ ] **Step 1: Discover affected call sites and tests**

Run: `grep -rn "EditorJs" app/ tests/ && grep -rln "Note::factory" tests/`
Note every file that seeds note content as a block array or asserts on block structure. Expected: `app/Models/Note.php`, `app/Support/EditorJs.php`, `NoteFactory`, and a handful of feature tests (`EntryViewTest`, timeline tests).

- [ ] **Step 2: Update the model**

In `app/Models/Note.php`: remove the `use App\Support\EditorJs;` import, remove `'content' => 'array',` from `casts()`, and change the card title line to:

```php
'title' => Str::limit($this->content, 80),
```

- [ ] **Step 3: Update the factory**

In `database/factories/NoteFactory.php`, replace the `content` definition with:

```php
'content' => fake()->sentences(2, true),
```

Keep the existing `occurred_at` definition as-is.

- [ ] **Step 4: Update NoteDetail.vue**

Replace the component body so it renders plaintext with preserved line breaks (match surrounding prose styling used by sibling detail components):

```vue
<script setup>
defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <p class="whitespace-pre-line text-base leading-relaxed">{{ entry.content }}</p>
</template>
```

- [ ] **Step 5: Fix tests found in Step 1**

In each test that seeds `Note::factory()->create(['content' => [/* blocks */]])` or asserts on block JSON, replace the content with a plain string (e.g. `'content' => 'Walked along the river this morning.'`) and update assertions to match the string / `Str::limit` card title.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS. If a note-related test still fails, the failure names the exact assertion to update; fix only note-content assertions.

- [ ] **Step 7: Build frontend + commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: notes store plaintext content instead of Editor.js blocks"
```

---

### Task 3: Notes vertical (actions + API resource)

**Files:**
- Create: `app/Actions/Notes/CreateNote.php`
- Create: `app/Actions/Notes/UpdateNote.php`
- Create: `app/Actions/Notes/DeleteNote.php`
- Create: `app/Http/Requests/Api/V1/StoreNoteRequest.php`
- Create: `app/Http/Requests/Api/V1/UpdateNoteRequest.php`
- Create: `app/Http/Resources/V1/NoteResource.php`
- Create: `app/Http/Controllers/Api/V1/NoteController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/NoteApiTest.php`

**Interfaces:**
- Consumes: plaintext `Note` from Task 2; v1 route group + token test convention from Task 1.
- Produces: `CreateNote::__invoke(array $attributes): Note`, `UpdateNote::__invoke(Note $note, array $attributes): Note`, `DeleteNote::__invoke(Note $note): void`. `NoteResource` shape: `{id, occurred_at, content, created_at, updated_at}`. Routes named `api.v1.notes.*`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/NoteApiTest.php`:

```php
<?php

use App\Models\Note;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('creates a note', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Espresso was dialled in perfectly today.',
        'occurred_at' => '2026-07-04 09:15:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.content', 'Espresso was dialled in perfectly today.');

    expect(Note::count())->toBe(1)
        ->and(Note::first()->timelineEntry)->not->toBeNull();
});

it('defaults occurred_at to now when omitted', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', ['content' => 'Quick thought.'])
        ->assertCreated();

    expect(Note::first()->occurred_at)->not->toBeNull();
});

it('validates content is required', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/notes', ['content' => 'nope'])->assertUnauthorized();

    expect(Note::count())->toBe(0);
});

it('lists notes newest first', function () {
    Note::factory()->create(['occurred_at' => '2026-07-01 10:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-03 10:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/notes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.occurred_at', fn ($v) => str_starts_with($v, '2026-07-03'));
});

it('shows, updates, and deletes a note', function () {
    $note = Note::factory()->create(['content' => 'Before']);

    $this->withToken('test-token')->getJson("/api/v1/notes/{$note->id}")
        ->assertOk()
        ->assertJsonPath('data.content', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/notes/{$note->id}", ['content' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.content', 'After');

    $this->withToken('test-token')->deleteJson("/api/v1/notes/{$note->id}")->assertNoContent();

    expect(Note::count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=NoteApiTest`
Expected: FAIL (404, routes missing).

- [ ] **Step 3: Implement the actions**

Create `app/Actions/Notes/CreateNote.php`:

```php
<?php

namespace App\Actions\Notes;

use App\Models\Note;

class CreateNote
{
    /**
     * @param  array{content: string, occurred_at?: string|null}  $attributes
     */
    public function __invoke(array $attributes): Note
    {
        return Note::create([
            'content' => $attributes['content'],
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
```

Create `app/Actions/Notes/UpdateNote.php`:

```php
<?php

namespace App\Actions\Notes;

use App\Models\Note;

class UpdateNote
{
    /**
     * @param  array{content?: string, occurred_at?: string}  $attributes
     */
    public function __invoke(Note $note, array $attributes): Note
    {
        $note->fill($attributes)->save();

        return $note->refresh();
    }
}
```

Create `app/Actions/Notes/DeleteNote.php`:

```php
<?php

namespace App\Actions\Notes;

use App\Models\Note;

class DeleteNote
{
    public function __invoke(Note $note): void
    {
        $note->delete();
    }
}
```

- [ ] **Step 4: Implement requests, resource, controller, routes**

Create `app/Http/Requests/Api/V1/StoreNoteRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

Create `app/Http/Requests/Api/V1/UpdateNoteRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'required', 'string', 'max:10000'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

Create `app/Http/Resources/V1/NoteResource.php`:

```php
<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'content' => $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

Create `app/Http/Controllers/Api/V1/NoteController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notes\CreateNote;
use App\Actions\Notes\DeleteNote;
use App\Actions\Notes\UpdateNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNoteRequest;
use App\Http\Requests\Api\V1\UpdateNoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return NoteResource::collection(
            Note::query()->orderByDesc('occurred_at')->paginate(25)
        );
    }

    public function store(StoreNoteRequest $request, CreateNote $createNote): JsonResponse
    {
        $note = $createNote($request->validated());

        return NoteResource::make($note)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Note $note): NoteResource
    {
        return NoteResource::make($note);
    }

    public function update(UpdateNoteRequest $request, Note $note, UpdateNote $updateNote): NoteResource
    {
        return NoteResource::make($updateNote($note, $request->validated()));
    }

    public function destroy(Note $note, DeleteNote $deleteNote): Response
    {
        $deleteNote($note);

        return response()->noContent();
    }
}
```

In `routes/api.php`, inside the v1 group, add:

```php
Route::apiResource('notes', \App\Http\Controllers\Api\V1\NoteController::class);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=NoteApiTest`
Expected: PASS (6 tests). The timeline-entry assertion passes for free because `TimelineEntryObserver` fires on `Note::create`.

- [ ] **Step 6: Full suite + commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: notes API vertical - actions, /api/v1/notes resource"
```

---

### Task 4: Canonicalise distance to bare `distance` in integer metres

Both distance columns become bare `distance` storing integer metres, mirroring bare `duration` in seconds. Display layers convert: activities show km, flights show miles.

**Files (from `grep -rln "distance_km\|distance_miles"`, 63 occurrences):**
- Create: `database/migrations/<timestamp>_canonicalise_distance_to_metres.php`
- Create: `app/Support/Distance.php` (PHP display conversions)
- Create: `resources/js/lib/distance.js` (JS display conversions)
- Modify: `app/Models/Activity.php`, `app/Models/Flight.php`
- Modify: `app/Http/Controllers/TimelineController.php` (month/day stats)
- Modify: `app/Console/Commands/Sync/StravaSync.php`, `app/Console/Commands/Fetch/EnrichFlights.php`
- Modify: `app/Services/LogoStream.php`, `app/Stories/FlightStory.php`, `app/Search/SearchSchema.php`
- Modify: `database/factories/ActivityFactory.php`, `database/factories/FlightFactory.php`
- Modify: `resources/js/Components/Entry/ActivityDetail.vue`, `resources/js/Components/Entry/FlightDetail.vue`
- Modify: tests: `AdvancedSearchTest`, `CalendarViewsTest`, `EnrichFlightsTest`, `EntryViewTest`, `FlightStoryTest`, `HealthHeartRateTest`, `Services/LogoStreamTest`, `StoryControllerTest`
- Test: `tests/Feature/DistanceCanonicalisationTest.php`

**Interfaces:**
- Produces: `activities.distance` and `flights.distance` (integer metres, nullable). `App\Support\Distance::km(?int $metres): ?float` (1 decimal), `Distance::miles(?int $metres): ?int` (rounded). JS: `metresToKm(metres)` (1 decimal number), `metresToMiles(metres)` (rounded integer) exported from `resources/js/lib/distance.js`. Task 6 stores/returns `distance` in metres.

**Conversion rules (apply everywhere):**
- `distance_km` value → metres: `round(value * 1000)`
- `distance_miles` value → metres: `round(value * 1609.344)`
- Display km: `metres / 1000` rounded to 1 decimal. Display miles: `round(metres / 1609.344)`.
- Strava's API returns metres natively: `StravaSync` must store the raw value (delete its to-km conversion, do not convert twice).

- [ ] **Step 1: Write the failing migration test**

Create `tests/Feature/DistanceCanonicalisationTest.php`:

```php
<?php

use App\Models\Activity;
use App\Models\Flight;
use App\Support\Distance;

it('stores activity and flight distance in integer metres', function () {
    $activity = Activity::factory()->create(['distance' => 5230]);
    $flight = Flight::factory()->create(['distance' => 1245632]);

    expect($activity->fresh()->distance)->toBe(5230)
        ->and($flight->fresh()->distance)->toBe(1245632);
});

it('converts metres for display', function () {
    expect(Distance::km(5230))->toBe(5.2)
        ->and(Distance::miles(1245632))->toBe(774)
        ->and(Distance::km(null))->toBeNull()
        ->and(Distance::miles(null))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DistanceCanonicalisationTest`
Expected: FAIL (`distance` column does not exist; `Distance` class not found).

- [ ] **Step 3: Write the migration**

Run `php artisan make:migration canonicalise_distance_to_metres --no-interaction`, then:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('distance_km', 'distance');
        });
        DB::table('activities')->whereNotNull('distance')->update([
            'distance' => DB::raw('CAST(ROUND(distance * 1000) AS INTEGER)'),
        ]);

        Schema::table('flights', function (Blueprint $table) {
            $table->renameColumn('distance_miles', 'distance');
        });
        DB::table('flights')->whereNotNull('distance')->update([
            'distance' => DB::raw('CAST(ROUND(distance * 1609.344) AS INTEGER)'),
        ]);
    }

    public function down(): void
    {
        DB::table('activities')->whereNotNull('distance')->update([
            'distance' => DB::raw('distance / 1000.0'),
        ]);
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('distance', 'distance_km');
        });

        DB::table('flights')->whereNotNull('distance')->update([
            'distance' => DB::raw('CAST(ROUND(distance / 1609.344) AS INTEGER)'),
        ]);
        Schema::table('flights', function (Blueprint $table) {
            $table->renameColumn('distance', 'distance_miles');
        });
    }
};
```

Run: `php artisan migrate --no-interaction`

- [ ] **Step 4: Create the conversion helpers**

Create `app/Support/Distance.php`:

```php
<?php

namespace App\Support;

class Distance
{
    public const METRES_PER_MILE = 1609.344;

    /**
     * Metres to kilometres for display, one decimal place.
     */
    public static function km(?int $metres): ?float
    {
        return $metres === null ? null : round($metres / 1000, 1);
    }

    /**
     * Metres to whole miles for display.
     */
    public static function miles(?int $metres): ?int
    {
        return $metres === null ? null : (int) round($metres / self::METRES_PER_MILE);
    }

    /**
     * Miles to integer metres for storage.
     */
    public static function fromMiles(float|int $miles): int
    {
        return (int) round($miles * self::METRES_PER_MILE);
    }

    /**
     * Kilometres to integer metres for storage.
     */
    public static function fromKm(float|int $km): int
    {
        return (int) round($km * 1000);
    }
}
```

Create `resources/js/lib/distance.js`:

```js
const METRES_PER_MILE = 1609.344;

// Metres to kilometres for display, one decimal place.
export function metresToKm(metres) {
    if (metres === null || metres === undefined) return null;
    return Math.round(metres / 100) / 10;
}

// Metres to whole miles for display.
export function metresToMiles(metres) {
    if (metres === null || metres === undefined) return null;
    return Math.round(metres / METRES_PER_MILE);
}
```

- [ ] **Step 5: Update every call site**

Run `grep -rn "distance_km\|distance_miles" app/ database/ resources/js tests/` and fix each occurrence with these rules:

- `app/Models/Activity.php`: rename attribute references to `distance`; anywhere the card/subtitle formats km, use `Distance::km($this->distance)`. Add `'distance' => 'integer'` to `casts()`.
- `app/Models/Flight.php`: rename to `distance` (fillable + card); the card subtitle mile string becomes `Distance::miles($this->distance)`; the `route.distance` card meta passes `Distance::miles($this->distance)` (frontend shows miles for flights). Add `'distance' => 'integer'` to `casts()`.
- `app/Http/Controllers/TimelineController.php`: `sum('distance_km')` becomes `sum('distance')` and the stat value converts via `Distance::km(...)` at the point the stat is built.
- `app/Console/Commands/Sync/StravaSync.php`: Strava returns metres; store the raw integer (`(int) round($stravaActivity['distance'])`), delete any division.
- `app/Console/Commands/Fetch/EnrichFlights.php` + `app/Services/LogoStream.php`: wherever a miles value was written to `distance_miles`, write `Distance::fromMiles($miles)` to `distance`.
- `app/Stories/FlightStory.php`: sums/rollups read `distance` (metres); convert once at the presentation edge with `Distance::miles(...)` so story stat labels stay in miles.
- `app/Search/SearchSchema.php`: replace the two suffixed fields with one `distance` field; keep the display suffix logic per type (km for activities, mi for flights) at render, not storage.
- `database/factories/ActivityFactory.php` / `FlightFactory.php`: `'distance' => fake()->numberBetween(1000, 20000)` (activities) and `'distance' => fake()->numberBetween(300000, 9000000)` (flights).
- `resources/js/Components/Entry/ActivityDetail.vue` / `FlightDetail.vue`: import from `../../lib/distance.js`; render `metresToKm(...)` / `metresToMiles(...)` where the raw column was shown.
- Tests listed above: update seeded attributes to `distance` (metres) and assertions to the converted display values.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS, including `DistanceCanonicalisationTest`. Fix any remaining `distance_km`/`distance_miles` reference the failures reveal; when done, `grep -rn "distance_km\|distance_miles" app/ resources/js` must return only the migration file.

- [ ] **Step 7: Refresh CSV seeds, build, commit**

The `data/*.csv` seeds still carry old headers; regenerate them with the existing export command (find it with `php artisan list | grep -i export`, expected `export:csv` / `export:all`):

```bash
php artisan export:all --no-interaction
npm run build
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: canonicalise distance to bare integer-metre column across activities and flights"
```

---

### Task 5: Units input parser

**Files:**
- Create: `app/Support/Units.php`
- Test: `tests/Unit/UnitsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Units::seconds(mixed $value): ?int` and `Units::metres(mixed $value): ?int`. Both return the canonical integer for ints/floats/numeric strings and recognised unit strings, `null` for anything unparseable (callers leave the original value in place so validation rejects it). Task 6's Form Requests call these in `prepareForValidation()`.

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/UnitsTest.php`:

```php
<?php

use App\Support\Units;

it('parses durations to seconds', function (mixed $input, ?int $expected) {
    expect(Units::seconds($input))->toBe($expected);
})->with([
    'plain int' => [8700, 8700],
    'numeric string' => ['8700', 8700],
    'hours and minutes' => ['2h 56m', 10560],
    'compact' => ['2h56m', 10560],
    'minutes only' => ['45m', 2700],
    'minutes word' => ['90 min', 5400],
    'hours decimal' => ['1.5h', 5400],
    'clock hms' => ['2:56:00', 10560],
    'clock ms' => ['56:30', 3390],
    'seconds suffix' => ['30s', 30],
    'garbage' => ['soon', null],
    'null' => [null, null],
]);

it('parses distances to metres', function (mixed $input, ?int $expected) {
    expect(Units::metres($input))->toBe($expected);
})->with([
    'plain int metres' => [5230, 5230],
    'numeric string' => ['5230', 5230],
    'km' => ['5.2 km', 5200],
    'km compact' => ['5.2km', 5200],
    'miles' => ['774 miles', 1245632],
    'mi' => ['774 mi', 1245632],
    'metres suffix' => ['1200 m', 1200],
    'garbage' => ['far away', null],
    'null' => [null, null],
]);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=UnitsTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement the parser**

Create `app/Support/Units.php`:

```php
<?php

namespace App\Support;

class Units
{
    /**
     * Normalise a duration input to integer seconds. Accepts integers/floats
     * (already seconds), numeric strings, "2h 56m" style component strings,
     * and "H:MM:SS" / "MM:SS" clock strings. Returns null when unparseable.
     */
    public static function seconds(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $value, $m)) {
            return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
        }

        $seconds = 0;
        $matched = false;
        $pattern = '/(\d+(?:\.\d+)?)\s*(h|hr|hrs|hour|hours|m|min|mins|minute|minutes|s|sec|secs|second|seconds)\b/';

        if (preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $amount = (float) $match[1];
                $seconds += match ($match[2][0]) {
                    'h' => $amount * 3600,
                    'm' => $amount * 60,
                    's' => $amount,
                };
                $matched = true;
            }
        }

        return $matched ? (int) round($seconds) : null;
    }

    /**
     * Normalise a distance input to integer metres. Accepts numbers (already
     * metres), numeric strings, and "5.2 km" / "774 miles" / "1200 m" style
     * strings. Returns null when unparseable.
     */
    public static function metres(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*(km|kilometre|kilometres|kilometer|kilometers|mi|mile|miles|m|metre|metres|meter|meters)$/', $value, $m)) {
            return null;
        }

        $amount = (float) $m[1];

        return match ($m[2][0].($m[2][1] ?? '')) {
            'km', 'ki' => Distance::fromKm($amount),
            'mi' => Distance::fromMiles($amount),
            default => (int) round($amount),
        };
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=UnitsTest`
Expected: PASS (all dataset cases).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Units.php tests/Unit/UnitsTest.php
git commit -m "feat: Units parser normalising duration/distance strings to canonical seconds/metres"
```

---

### Task 6: Flights vertical (actions + API resource + idempotent create + flexible units)

**Files:**
- Create: `app/Actions/Flights/CreateFlight.php`
- Create: `app/Actions/Flights/UpdateFlight.php`
- Create: `app/Actions/Flights/DeleteFlight.php`
- Create: `app/Http/Requests/Api/V1/StoreFlightRequest.php`
- Create: `app/Http/Requests/Api/V1/UpdateFlightRequest.php`
- Create: `app/Http/Resources/V1/FlightResource.php`
- Create: `app/Http/Controllers/Api/V1/FlightController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/FlightApiTest.php`

**Interfaces:**
- Consumes: v1 route group + token test convention (Task 1); canonical `distance` metres column (Task 4); `Units::seconds()`/`Units::metres()` (Task 5); `Flight` relations `airline()`, `origin()`, `destination()`.
- Produces: `CreateFlight::__invoke(array $attributes): array{flight: Flight, created: bool}` (idempotent upsert on natural key `occurred_at` + `flight_number` + `origin_iata` + `destination_iata`); `FlightResource` returns canonical `duration` (seconds) and `distance` (metres). Routes `api.v1.flights.*`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/FlightApiTest.php`:

```php
<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');

    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
    Airport::factory()->create(['iata_code' => 'LGW', 'name' => 'London Gatwick']);
    Airport::factory()->create(['iata_code' => 'MAD', 'name' => 'Madrid Barajas']);
});

function validFlightPayload(): array
{
    return [
        'occurred_at' => '2026-08-12 10:35:00',
        'flight_number' => '2718',
        'airline_icao' => 'BAW',
        'origin_iata' => 'LGW',
        'destination_iata' => 'MAD',
        'duration' => 8700,
        'distance' => 1245632,
        'cabin_class' => 'economy',
        'reason' => 'holiday',
        'departure_timezone' => 'Europe/London',
        'arrival_timezone' => 'Europe/Madrid',
    ];
}

it('creates a flight with airline and airports resolved', function () {
    $this->withToken('test-token')->postJson('/api/v1/flights', validFlightPayload())
        ->assertCreated()
        ->assertJsonPath('data.origin.iata', 'LGW')
        ->assertJsonPath('data.airline.name', 'British Airways')
        ->assertJsonPath('data.distance', 1245632);

    expect(Flight::count())->toBe(1)
        ->and(Flight::first()->timelineEntry)->not->toBeNull();
});

it('accepts human-friendly duration and distance units', function () {
    $payload = array_merge(validFlightPayload(), [
        'duration' => '2h 25m',
        'distance' => '774 miles',
    ]);

    $this->withToken('test-token')->postJson('/api/v1/flights', $payload)
        ->assertCreated()
        ->assertJsonPath('data.duration', 8700)
        ->assertJsonPath('data.distance', 1245632);
});

it('rejects unparseable unit strings', function () {
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['duration' => 'a while']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration']);
});

it('is idempotent on the natural key', function () {
    $this->withToken('test-token')->postJson('/api/v1/flights', validFlightPayload())->assertCreated();
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['distance' => 1245700]))
        ->assertOk();

    expect(Flight::count())->toBe(1)
        ->and(Flight::first()->distance)->toBe(1245700);
});

it('rejects unknown airports and airlines', function () {
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['origin_iata' => 'XXX']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origin_iata']);

    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['airline_icao' => 'ZZZ']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['airline_icao']);
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/flights', validFlightPayload())->assertUnauthorized();

    expect(Flight::count())->toBe(0);
});

it('updates and deletes a flight', function () {
    $flight = Flight::factory()->create(['cabin_class' => 'economy', 'origin_iata' => 'LGW', 'destination_iata' => 'MAD', 'airline_icao' => 'BAW']);

    $this->withToken('test-token')->patchJson("/api/v1/flights/{$flight->id}", ['cabin_class' => 'business'])
        ->assertOk()
        ->assertJsonPath('data.cabin_class', 'business');

    $this->withToken('test-token')->deleteJson("/api/v1/flights/{$flight->id}")->assertNoContent();

    expect(Flight::count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=FlightApiTest`
Expected: FAIL (404, routes missing). If `AirlineFactory`/`AirportFactory` lack the attributes used above, add those keys to the factory definitions rather than changing the test.

- [ ] **Step 3: Implement the actions**

Create `app/Actions/Flights/CreateFlight.php`:

```php
<?php

namespace App\Actions\Flights;

use App\Models\Flight;
use Illuminate\Support\Arr;

class CreateFlight
{
    /**
     * Idempotent create: retried submissions of the same flight update in
     * place instead of duplicating, keyed on the flight's natural identity.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{flight: Flight, created: bool}
     */
    public function __invoke(array $attributes): array
    {
        $key = [
            'occurred_at' => $attributes['occurred_at'],
            'flight_number' => $attributes['flight_number'],
            'origin_iata' => $attributes['origin_iata'],
            'destination_iata' => $attributes['destination_iata'],
        ];

        $flight = Flight::query()->where($key)->first();
        $created = $flight === null;

        $flight = Flight::updateOrCreate($key, Arr::except($attributes, array_keys($key)));

        return ['flight' => $flight->refresh(), 'created' => $created];
    }
}
```

Create `app/Actions/Flights/UpdateFlight.php`:

```php
<?php

namespace App\Actions\Flights;

use App\Models\Flight;

class UpdateFlight
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Flight $flight, array $attributes): Flight
    {
        $flight->fill($attributes)->save();

        return $flight->refresh();
    }
}
```

Create `app/Actions/Flights/DeleteFlight.php`:

```php
<?php

namespace App\Actions\Flights;

use App\Models\Flight;

class DeleteFlight
{
    public function __invoke(Flight $flight): void
    {
        $flight->delete();
    }
}
```

- [ ] **Step 4: Implement requests, resource, controller, routes**

Create `app/Http/Requests/Api/V1/StoreFlightRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlightRequest extends FormRequest
{
    /**
     * Normalise flexible unit strings ("2h 25m", "774 miles") to canonical
     * seconds/metres. Unparseable values are left as-is so the integer rules
     * reject them with a validation error.
     */
    protected function prepareForValidation(): void
    {
        $normalised = [];

        if ($this->has('duration')) {
            $normalised['duration'] = Units::seconds($this->input('duration')) ?? $this->input('duration');
        }

        if ($this->has('distance')) {
            $normalised['distance'] = Units::metres($this->input('distance')) ?? $this->input('distance');
        }

        $this->merge($normalised);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'flight_number' => ['required', 'string', 'max:10'],
            'airline_icao' => ['required', 'string', 'exists:airlines,icao_code'],
            'origin_iata' => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
            'destination_iata' => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'distance' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cabin_class' => ['sometimes', 'nullable', 'string', 'max:30'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:100'],
            'departure_timezone' => ['sometimes', 'nullable', 'timezone'],
            'arrival_timezone' => ['sometimes', 'nullable', 'timezone'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
```

Create `app/Http/Requests/Api/V1/UpdateFlightRequest.php` (same `prepareForValidation`, all rules `sometimes`):

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFlightRequest extends FormRequest
{
    /**
     * Normalise flexible unit strings to canonical seconds/metres, leaving
     * unparseable values for the integer rules to reject.
     */
    protected function prepareForValidation(): void
    {
        $normalised = [];

        if ($this->has('duration')) {
            $normalised['duration'] = Units::seconds($this->input('duration')) ?? $this->input('duration');
        }

        if ($this->has('distance')) {
            $normalised['distance'] = Units::metres($this->input('distance')) ?? $this->input('distance');
        }

        $this->merge($normalised);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date'],
            'flight_number' => ['sometimes', 'string', 'max:10'],
            'airline_icao' => ['sometimes', 'string', 'exists:airlines,icao_code'],
            'origin_iata' => ['sometimes', 'string', 'size:3', 'exists:airports,iata_code'],
            'destination_iata' => ['sometimes', 'string', 'size:3', 'exists:airports,iata_code'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'distance' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cabin_class' => ['sometimes', 'nullable', 'string', 'max:30'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:100'],
            'departure_timezone' => ['sometimes', 'nullable', 'timezone'],
            'arrival_timezone' => ['sometimes', 'nullable', 'timezone'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
```

Create `app/Http/Resources/V1/FlightResource.php`:

```php
<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlightResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'flight_number' => $this->flight_number,
            'airline' => $this->whenLoaded('airline', fn () => [
                'icao' => $this->airline->icao_code,
                'iata' => $this->airline->iata_code,
                'name' => $this->airline->name,
            ]),
            'origin' => $this->whenLoaded('origin', fn () => [
                'iata' => $this->origin->iata_code,
                'name' => $this->origin->name,
            ]),
            'destination' => $this->whenLoaded('destination', fn () => [
                'iata' => $this->destination->iata_code,
                'name' => $this->destination->name,
            ]),
            'duration' => $this->duration,
            'distance' => $this->distance,
            'cabin_class' => $this->cabin_class,
            'reason' => $this->reason,
            'departure_timezone' => $this->departure_timezone,
            'arrival_timezone' => $this->arrival_timezone,
            'departed_local' => $this->departed_local,
            'arrived_local' => $this->arrived_local,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

Create `app/Http/Controllers/Api/V1/FlightController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Flights\CreateFlight;
use App\Actions\Flights\DeleteFlight;
use App\Actions\Flights\UpdateFlight;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFlightRequest;
use App\Http\Requests\Api\V1\UpdateFlightRequest;
use App\Http\Resources\V1\FlightResource;
use App\Models\Flight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FlightController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return FlightResource::collection(
            Flight::query()
                ->with(['airline', 'origin', 'destination'])
                ->orderByDesc('occurred_at')
                ->paginate(25)
        );
    }

    public function store(StoreFlightRequest $request, CreateFlight $createFlight): JsonResponse
    {
        $result = $createFlight($request->validated());

        return FlightResource::make($result['flight']->load(['airline', 'origin', 'destination']))
            ->response()
            ->setStatusCode($result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function show(Flight $flight): FlightResource
    {
        return FlightResource::make($flight->load(['airline', 'origin', 'destination']));
    }

    public function update(UpdateFlightRequest $request, Flight $flight, UpdateFlight $updateFlight): FlightResource
    {
        return FlightResource::make(
            $updateFlight($flight, $request->validated())->load(['airline', 'origin', 'destination'])
        );
    }

    public function destroy(Flight $flight, DeleteFlight $deleteFlight): Response
    {
        $deleteFlight($flight);

        return response()->noContent();
    }
}
```

In `routes/api.php`, inside the v1 group:

```php
Route::apiResource('flights', \App\Http\Controllers\Api\V1\FlightController::class);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=FlightApiTest`
Expected: PASS (7 tests).

- [ ] **Step 6: Full suite + commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: flights API vertical with idempotent create and flexible unit input"
```

---

### Task 7: Standard index filters (from/to) on both resources

**Files:**
- Create: `app/Http/Requests/Api/V1/ListRequest.php`
- Modify: `app/Http/Controllers/Api/V1/NoteController.php` (index)
- Modify: `app/Http/Controllers/Api/V1/FlightController.php` (index)
- Test: `tests/Feature/Api/ListFiltersTest.php`

**Interfaces:**
- Consumes: controllers from Tasks 3 and 6.
- Produces: every v1 index accepts `from` (date), `to` (date), `per_page` (1-100, default 25) with identical semantics. This is the contract Plan 4 replicates for the remaining types.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/ListFiltersTest.php`:

```php
<?php

use App\Models\Note;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('filters notes by from and to dates inclusively', function () {
    Note::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Note::factory()->create(['occurred_at' => '2026-06-15 08:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/notes?from=2026-06-10&to=2026-06-30')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('respects per_page and caps at 100', function () {
    Note::factory()->count(3)->create();

    $this->withToken('test-token')->getJson('/api/v1/notes?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);

    $this->withToken('test-token')->getJson('/api/v1/notes?per_page=500')->assertUnprocessable();
});

it('rejects a from date after the to date', function () {
    $this->withToken('test-token')->getJson('/api/v1/notes?from=2026-07-01&to=2026-06-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ListFiltersTest`
Expected: FAIL (filters ignored; per_page=500 returns 200).

- [ ] **Step 3: Implement the shared ListRequest**

Create `app/Http/Requests/Api/V1/ListRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date', 'before_or_equal:to'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Apply the standard occurred_at window to any resource query.
     */
    public function applyTo(Builder $query): Builder
    {
        return $query
            ->when($this->date('from'), fn (Builder $q, $from) => $q->where('occurred_at', '>=', $from->startOfDay()))
            ->when($this->date('to'), fn (Builder $q, $to) => $q->where('occurred_at', '<=', $to->endOfDay()));
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}
```

- [ ] **Step 4: Use it in both index methods**

`NoteController::index` becomes:

```php
public function index(ListRequest $request): AnonymousResourceCollection
{
    return NoteResource::collection(
        $request->applyTo(Note::query())
            ->orderByDesc('occurred_at')
            ->paginate($request->perPage())
    );
}
```

`FlightController::index` becomes:

```php
public function index(ListRequest $request): AnonymousResourceCollection
{
    return FlightResource::collection(
        $request->applyTo(Flight::query()->with(['airline', 'origin', 'destination']))
            ->orderByDesc('occurred_at')
            ->paginate($request->perPage())
    );
}
```

Add `use App\Http\Requests\Api\V1\ListRequest;` to both controllers.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ListFiltersTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Full suite, build, commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: standard from/to/per_page index filters via shared ListRequest"
```

---

## Self-Review Notes

- Spec coverage: env token auth + fail-closed contract (T1), notes plaintext conversion (T2), notes vertical (T3), distance canonicalisation to metres (T4), flexible unit parsing (T5), flights vertical with relations + idempotency + unit input (T6), uniform list filters (T7). MCP, articles rich text, remaining types, sync migration are explicitly out of scope (Plans 2-4).
- Decisions (user, 2026-07-04): single static `API_TOKEN` in env, no Sanctum, no per-client abilities. Distance stored as bare `distance` in integer metres everywhere (supersedes `distance_km`/`distance_miles`); display converts to km/miles. API accepts flexible unit strings, responds canonical only.
- Type consistency: `CreateNote` returns `Note`; `CreateFlight` returns `array{flight: Flight, created: bool}` consumed exactly by `FlightController::store`. `Units::seconds`/`Units::metres` return `?int` and are used with `?? $this->input(...)` in both flight Form Requests. `Distance::fromMiles`/`fromKm` used by `Units` and `EnrichFlights`. `ListRequest::applyTo(Builder): Builder` used identically in both controllers. 774 miles = 1,245,632 m and 2h 25m = 8,700 s used consistently in Task 5 and Task 6 tests.
- Known follow-ups deliberately excluded (YAGNI): attachment upload endpoint, articles/pages resources, per-type facet filters beyond dates, converting `fuel.odometer` (a raw dashboard reading, left in miles).
