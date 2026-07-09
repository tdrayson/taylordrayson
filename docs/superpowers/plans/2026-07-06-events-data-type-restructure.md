# Events Data Type Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Evolve the existing `event` timeline type to hold the enriched, multi-day, categorised events dataset and load the real 90 events as a DB-shaped `data/events.csv`.

**Architecture:** Add columns to the `events` table (dates/end, `company`, `url`, `meta` JSON), rename `notes`→`description`, drop `address`/`ticket_price`. Reuse the existing generic `import:csv` command (already registered for `event`) by providing a `data/events.csv` whose headers match the model's fillable, with loose fields packed into a `meta` JSON column. Update the factory and the read-only `EventDetail.vue` to the new shape.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, Inertia v3 + Vue 3, Tailwind v4.

## Global Constraints

- PHP 8.4; explicit return types on all methods; curly braces on all control structures; constructor property promotion.
- No `env()` outside config files.
- Run `vendor/bin/pint --dirty --format agent` after editing PHP, before each commit.
- Tests are Pest: `php artisan test --compact --filter=<name>`. Feature tests use `RefreshDatabase`.
- Stored sub-types (`type`) are kebab-case. `occurred_at` is local wall-clock; `timezone` is a per-row IANA string; never shift the stored time.
- Do not change dependencies.

---

### Task 1: Evolve the `events` schema and `Event` model

**Files:**
- Create: `database/migrations/2026_07_06_000000_restructure_events_table.php`
- Modify: `app/Models/Event.php`
- Test: `tests/Feature/EventTest.php`

**Interfaces:**
- Produces: `events` columns `occurred_at, ends_at, all_day, type, name, company, venue_name, city, country, latitude, longitude, url, description, timezone, meta`; `Event` fillable + casts (`ends_at`=datetime, `all_day`=boolean, `meta`=array). Columns `address`, `ticket_price`, `notes` no longer exist (`notes` renamed to `description`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EventTest.php`:

```php
<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('stores the restructured event fields', function () {
    $event = Event::create([
        'occurred_at' => '2025-06-05 00:00:00',
        'ends_at' => '2025-06-07 00:00:00',
        'all_day' => true,
        'type' => 'conference',
        'name' => 'WordCamp Europe 2025',
        'company' => null,
        'venue_name' => 'Congress Center Basel',
        'city' => 'Basel',
        'country' => 'Switzerland',
        'latitude' => '47.562481',
        'longitude' => '7.599253',
        'url' => 'https://europe.wordcamp.org/2025/',
        'description' => 'Went with dad.',
        'timezone' => 'Europe/Zurich',
        'meta' => ['seat' => 'Stalls F9', 'place_id' => 'abc123'],
    ])->refresh();

    expect($event->all_day)->toBeTrue()
        ->and($event->ends_at->format('Y-m-d'))->toBe('2025-06-07')
        ->and($event->meta['seat'])->toBe('Stalls F9')
        ->and($event->description)->toBe('Went with dad.');
});

it('drops the retired event columns', function () {
    expect(Schema::hasColumn('events', 'ticket_price'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'address'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'notes'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventTest`
Expected: FAIL — new fields not fillable / `ends_at` column missing.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_06_000000_restructure_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('ends_at')->nullable()->after('occurred_at');
            $table->boolean('all_day')->default(false)->after('ends_at');
            $table->string('company')->nullable()->after('name');
            $table->string('url')->nullable()->after('country');
            $table->json('meta')->nullable()->after('url');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->renameColumn('notes', 'description');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['address', 'ticket_price']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('venue_name');
            $table->decimal('ticket_price', 8, 2)->nullable()->after('longitude');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->renameColumn('description', 'notes');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['ends_at', 'all_day', 'company', 'url', 'meta']);
        });
    }
};
```

- [ ] **Step 4: Update the model**

Replace the `#[Fillable([...])]` attribute and `casts()` method in `app/Models/Event.php`:

```php
#[Fillable([
    'occurred_at',
    'ends_at',
    'all_day',
    'type',
    'name',
    'company',
    'venue_name',
    'city',
    'country',
    'latitude',
    'longitude',
    'url',
    'description',
    'timezone',
    'meta',
])]
```

```php
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'meta' => 'array',
        ];
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventTest`
Expected: PASS (both tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_06_000000_restructure_events_table.php app/Models/Event.php tests/Feature/EventTest.php
git commit -m "feat: restructure events table for enriched dataset"
```

---

### Task 2: Widen the `EventFactory` category set

**Files:**
- Modify: `database/factories/EventFactory.php`
- Test: `tests/Feature/EventTest.php` (append)

**Interfaces:**
- Consumes: `Event` fillable from Task 1.
- Produces: `EventFactory::definition()` emitting a `type` from the full category set plus `timezone` `Europe/London`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/EventTest.php`:

```php
it('builds events across the expanded category set', function () {
    foreach (['musical', 'magic', 'sport', 'convention', 'conference'] as $type) {
        $event = Event::factory()->create(['type' => $type]);
        expect($event->type)->toBe($type)
            ->and($event->timezone)->toBe('Europe/London');
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="expanded category"`
Expected: FAIL — factory has no `timezone`, and `create(['type' => 'sport'])` produces a factory `name` only for the 4 original types (nullable name is fine, but `timezone` assertion fails).

- [ ] **Step 3: Widen the factory**

Replace the `NAMES_BY_TYPE` constant and `definition()` in `database/factories/EventFactory.php`:

```php
    /**
     * @var array<string, array<int, string>>
     */
    private const NAMES_BY_TYPE = [
        'concert' => ['Arctic Monkeys', 'Coldplay', 'The 1975', 'Sam Fender'],
        'musical' => ['Hamilton', 'Wicked', 'Six the Musical', 'Starlight Express'],
        'theatre' => ['War Horse', 'Present Laughter', 'The Curious Incident'],
        'magic' => ['Penn & Teller', 'Derren Brown', 'The Illusionists'],
        'comedy' => ['No Such Thing As A Fish', 'Max Fosh', 'Jimmy Carr'],
        'circus' => ['Cirque du Soleil', 'Cirque Berserk!'],
        'immersive' => ['Secret Cinema', 'Phantom Peak'],
        'sport' => ['Surrey vs Kent', 'Birmingham City vs Blackpool'],
        'festival' => ['Godstoneberry Beer Festival', 'Oxted Beer Festival'],
        'convention' => ['Blackpool Magic Convention', 'London Brick Festival'],
        'conference' => ['WordCamp Europe'],
        'exhibition' => ['The Photography Show', 'Ideal Home Show'],
        'dance' => ['Spirit of the Dance', 'Lord of the Dance'],
    ];
```

```php
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(self::NAMES_BY_TYPE));

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => $type,
            'name' => fake()->randomElement(self::NAMES_BY_TYPE[$type]),
            'venue_name' => fake()->randomElement(self::VENUES),
            'city' => fake()->randomElement(['London', 'Manchester', 'Brighton', 'Bristol']),
            'country' => 'United Kingdom',
            'timezone' => 'Europe/London',
        ];
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventTest`
Expected: PASS (all three tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/factories/EventFactory.php tests/Feature/EventTest.php
git commit -m "feat: widen event factory to the full category set"
```

---

### Task 3: Generate `data/events.csv` and load it via `import:csv`

**Files:**
- Create: `data/events.csv` (generated; DB-shaped)
- Modify: `app/Console/Commands/Import/ImportAll.php`
- Test: `tests/Feature/ImportEventsTest.php`

**Interfaces:**
- Consumes: `Event` fillable + casts (Task 1); the existing `import:csv {file} {type}` command (maps headers→fillable, JSON-decodes `array`-cast columns, `''`/`'n/a'`→null).
- Produces: `data/events.csv` with header row exactly `occurred_at,ends_at,all_day,type,name,company,venue_name,city,country,latitude,longitude,url,description,timezone,meta`; `data/events.csv` registered in `ImportAll::$imports`.

> **Note on idempotency (spec deviation):** the spec described an idempotent upsert. To stay DRY, we reuse the existing `import:csv` which `create()`s rows and is designed to run against a fresh DB (`import:all --fresh`), matching how flights/activities load. We do **not** add a bespoke upsert.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportEventsTest.php`:

```php
<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the events dataset from data/events.csv', function () {
    $this->artisan('import:csv', [
        'file' => base_path('data/events.csv'),
        'type' => 'event',
    ])->assertSuccessful();

    expect(Event::count())->toBeGreaterThan(80);

    $wceu = Event::where('name', 'WordCamp Europe 2025')->firstOrFail();
    expect($wceu->all_day)->toBeTrue()
        ->and($wceu->ends_at)->not->toBeNull()
        ->and($wceu->type)->toBe('conference')
        ->and($wceu->timezone)->toBe('Europe/Zurich');

    $panto = Event::where('type', 'theatre')
        ->whereNotNull('company')
        ->where('company', 'Sanderstead Dramatic Club')
        ->first();
    expect($panto)->not->toBeNull();

    $withSeat = Event::whereNotNull('meta')->get()
        ->first(fn (Event $e): bool => ! empty($e->meta['seat'] ?? null));
    expect($withSeat)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ImportEventsTest`
Expected: FAIL — `data/events.csv` does not exist.

- [ ] **Step 3: Generate `data/events.csv` from the curated dataset**

The curated source is the structured CSV produced during design (`Taylor's Events - events_structured.csv`). Transform it into DB-shaped columns. Run this from the repo root, pointing `SRC` at the curated file:

```bash
python3 - <<'PY'
import csv, re, json
SRC="/Users/taylordrayson/Downloads/Taylor's Events - events_structured.csv"
OUT="data/events.csv"

def isodate(d):
    d=d.strip()
    if re.match(r'\d{2}/\d{2}/\d{4}$', d):
        dd,mm,yy=d.split("/"); return f"{yy}-{mm}-{dd}"
    return d  # already YYYY-MM-DD

def dt(date, time):
    date=isodate(date)
    if not date: return ""
    t=(time or "").strip()
    if not t: t="00:00:00"
    elif len(t)==5: t=t+":00"
    return f"{date} {t}"

COLS=["occurred_at","ends_at","all_day","type","name","company","venue_name",
      "city","country","latitude","longitude","url","description","timezone","meta"]

with open(SRC, newline='') as f: rows=list(csv.DictReader(f))
out=[]
for r in rows:
    all_day = "1" if r["all_day"].strip().lower()=="yes" else "0"
    occurred = dt(r["start_date"], "" if all_day=="1" else r["start_time"])
    ends = dt(r["end_date"], "" if all_day=="1" else r["end_time"])
    meta={}
    if r.get("seat","").strip(): meta["seat"]=r["seat"].strip()
    if r.get("place_id","").strip(): meta["place_id"]=r["place_id"].strip()
    if r.get("formatted_address","").strip(): meta["formatted_address"]=r["formatted_address"].strip()
    out.append({
        "occurred_at":occurred,"ends_at":ends,"all_day":all_day,
        "type":re.sub(r'[^a-z0-9]+','-',r["category"].strip().lower()).strip('-'),
        "name":r["name"].strip(),"company":r["company"].strip(),
        "venue_name":r["venue"].strip(),"city":r["city"].strip(),"country":r["country"].strip(),
        "latitude":r["latitude"].strip(),"longitude":r["longitude"].strip(),
        "url":r["url"].strip(),"description":r["description"].strip(),
        "timezone":r["timezone"].strip(),
        "meta": json.dumps(meta) if meta else "",
    })
with open(OUT,"w",newline='') as f:
    w=csv.DictWriter(f, fieldnames=COLS); w.writeheader(); w.writerows(out)
print(f"wrote {len(out)} rows to {OUT}")
PY
```

Verify the header and a sample row:

```bash
head -2 data/events.csv
```

Expected first line: `occurred_at,ends_at,all_day,type,name,company,venue_name,city,country,latitude,longitude,url,description,timezone,meta`

- [ ] **Step 4: Register the file in `import:all`**

In `app/Console/Commands/Import/ImportAll.php`, add to the `$imports` array (after the `fuel` entry):

```php
        ['file' => 'data/events.csv', 'type' => 'event'],
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ImportEventsTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add data/events.csv app/Console/Commands/Import/ImportAll.php tests/Feature/ImportEventsTest.php
git commit -m "feat: load the real events dataset via import:csv"
```

---

### Task 4: Update `EventDetail.vue` to the new shape

**Files:**
- Modify: `resources/js/Components/Entry/EventDetail.vue`

**Interfaces:**
- Consumes: entry props `type, company, venue_name, city, country, url, description, meta` (with `meta.seat`), `occurred_at`, `ends_at`, `all_day`.

> No unit test — this is a read-only presentational component. It is covered by the Pest browser smoke test that visits entry pages. The change removes references to the dropped `address`/`ticket_price`/`notes` so those pages keep rendering.

- [ ] **Step 1: Replace the component**

Replace `resources/js/Components/Entry/EventDetail.vue` with:

```vue
<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import { titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Loose, display-only details live in the meta JSON column (seat, geocoding extras).
const seat = computed(() => props.entry.meta?.seat ?? null);

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Company', value: props.entry.company },
    { label: 'Venue', value: props.entry.venue_name },
    { label: 'City', value: props.entry.city },
    { label: 'Country', value: props.entry.country },
    { label: 'Seat', value: seat.value },
]);
</script>

<template>
    <div class="space-y-8">
        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="Notes" />
            <p class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>

        <div v-if="entry.url">
            <ExternalLink :href="entry.url">More about this event</ExternalLink>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run build`
Expected: builds without errors referencing `EventDetail.vue`.

- [ ] **Step 3: Run the browser smoke test (if present)**

Run: `php artisan test --compact --filter=Smoke`
Expected: PASS (event entry pages render).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Entry/EventDetail.vue
git commit -m "feat: event detail view uses company, seat and description"
```

---

## Notes on verification of the whole slice

After all tasks: `php artisan migrate:fresh` then `php artisan import:all` should load `data/events.csv` alongside the other types, and `/events` plus the new taxonomy pages (`/events/sport`, `/events/convention`, …) should resolve with real data. `ExternalLink.vue` is the existing wrapper for third-party links (adds ", opens in a new tab"); confirm its import path matches the repo before Task 4 Step 1.
