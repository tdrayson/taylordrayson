# Formatting Settings (Project B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Visitor-configurable distance (`mi`/`km`) and weight (`kg`/`lbs`) display that updates live, driven by Project A's `defineSetting` store via a new reactive `useFormat()` composable and a Formatting section in the settings modal.

**Architecture:** Pure conversion primitives in `lib/distance.js` + `lib/format.js` (take a value, return a number — no store coupling). A `composables/useFormat.js` defines the two settings via `defineSetting` and returns formatters that read `settingRef.value` **inside the function body**, so calling them in a Vue template/computed makes every consumer re-render live when the setting changes. A reusable `SettingToggle.vue` segmented control wires the settings into a new Formatting section of `SettingsModal.vue`; the display call sites migrate from direct `lib` imports to the composable.

**Tech Stack:** Laravel 13 + Inertia v3 + Vue 3 (client-only, no SSR), Tailwind v4, Pest 4 (browser plugin present).

## Global Constraints

- **Commit policy:** per-task commits on `feature/settings-panel` only (Project B stacks on Project A / PR #9); no push/PR without asking. NO "Claude-Session" trailer or attribution. Never stage `todo.md`, `.superpowers/`, `public/twemoji/`, or `.svg`.
- No new dependencies.
- No arbitrary Tailwind bracket values (standard scale / existing theme tokens). Comment non-obvious Vue/JS. Single root element per Vue component.
- **Reactivity rule:** formatters MUST read `settingRef.value` inside their own body (not capture it once), so template/computed callers re-render on change. Do not pass the raw value in as a prop that's read once.
- a11y: `SettingToggle` is a `role="radiogroup"` with a group `aria-label`; each option is `role="radio"` + `aria-checked` + a specific `aria-label`; every hover has a mirrored `focus-visible` ring.
- **Source-unit care:** activity distance props are in **metres**; the FeedItem flight `route.distance` is in **miles**. Use the metres path (`distance`/`distanceParts`) for metres sources and `distanceFromMiles` for the miles source — never double-convert.
- Defaults reproduce today's output exactly (`mi`, `kg`); nothing changes visibly until a visitor toggles.
- **Time and date are OUT OF SCOPE** (deferred to Project B.2 — entry timestamps are server-formatted per the timezone model). Do not add `timeFormat`/`dateFormat`, and do not touch `clock`/`dateLong`/`dateTime`/`useClock.js`.
- Browser tests assert the RENDERED DOM element/attribute, never text that also appears in the Inertia `data-page` props JSON (known false-positive trap).

---

### Task 1: Primitives + `useFormat` composable + `SettingToggle` + Formatting section

**Files:**
- Modify: `resources/js/lib/distance.js` (add `metresToKm`, `milesToKm`)
- Modify: `resources/js/lib/format.js` (add `kgToLbs`)
- Create: `resources/js/composables/useFormat.js`
- Create: `resources/js/Components/Layout/SettingToggle.vue`
- Modify: `resources/js/Components/Layout/SettingsModal.vue` (add a Formatting section with two toggles)
- Test: `tests/Browser/FormattingSettingsTest.php`

**Interfaces:**
- Consumes: `defineSetting(key, fallback, allowed?)` from `resources/js/useSettings.js` (Project A) → `{ value: Ref, set(v) }`. `number(value, fractionDigits)` and `metresToMiles(metres, precision)` already exist.
- Produces:
  - `metresToKm(metres, precision = 0) → number|null`, `milesToKm(miles, precision = 0) → number|null` (in `distance.js`).
  - `kgToLbs(kg) → number` (in `format.js`; unrounded — display rounding happens in the formatter).
  - `useFormat()` → `{ distance, distanceParts, distanceFromMiles, weight, distanceUnit, setDistanceUnit, weightUnit, setWeightUnit }` where:
    - `distance(metres, precision = 0) → string|null` e.g. `"3.1 mi"` / `"5 km"`.
    - `distanceParts(metres, precision = 0) → { value: string, unit: 'mi'|'km' }|null` (for StatGrid's `<abbr>`).
    - `distanceFromMiles(miles, precision = 0) → string|null` (miles-source, e.g. FeedItem flight note).
    - `weight(kg, precision = 'auto') → string|null` e.g. `"100 kg"` / `"220.5 lbs"` (`'auto'` = 1dp if fractional, else 0).
    - `distanceUnit` / `weightUnit` are the reactive `Ref`s; `setDistanceUnit` / `setWeightUnit` are their setters.
  - `SettingToggle.vue` — a segmented radiogroup control.

- [ ] **Step 1: Add distance conversions to `lib/distance.js`**

Append to `resources/js/lib/distance.js` (which currently exports `metresToMiles` and defines `METRES_PER_MILE`):
```js
// Metres to kilometres for display. Whole km by default; pass a precision for
// short distances where tenths matter. Mirrors metresToMiles so the settings
// layer can swap units without changing call sites.
export function metresToKm(metres, precision = 0) {
    if (metres === null || metres === undefined) return null;
    const factor = 10 ** precision;
    return Math.round((metres / 1000) * factor) / factor;
}

// Miles to kilometres, for call sites that already hold a mile value (e.g. a
// flight's route.distance) rather than raw metres.
export function milesToKm(miles, precision = 0) {
    if (miles === null || miles === undefined) return null;
    const factor = 10 ** precision;
    return Math.round((miles * 1.609344) * factor) / factor;
}
```

- [ ] **Step 2: Add `kgToLbs` to `lib/format.js`**

Add to `resources/js/lib/format.js` (near `number()`):
```js
// Kilograms to pounds. Returns the unrounded value; the formatter applies
// display rounding via number(), matching how raw kg is rounded at render.
export function kgToLbs(kg) {
    return Number(kg) * 2.20462;
}
```

- [ ] **Step 3: Write `composables/useFormat.js`**

Create `resources/js/composables/useFormat.js`:
```js
import { defineSetting } from '../useSettings';
import { number } from '../lib/format';
import { kgToLbs } from '../lib/format';
import { metresToMiles, metresToKm, milesToKm } from '../lib/distance';

// Two reactive, localStorage-backed settings (Project A's factory). Module-level
// so every useFormat() consumer shares one source. Values are whitelisted; an
// out-of-range stored value falls back to the default.
const distanceUnitDef = defineSetting('distanceUnit', 'mi', ['mi', 'km']);
const weightUnitDef = defineSetting('weightUnit', 'kg', ['kg', 'lbs']);

/**
 * Reactive-aware display formatters. Each reads its setting's `.value` INSIDE
 * the function body, so calling it in a template/computed registers the setting
 * as a render dependency and the view updates live when the visitor toggles.
 */
export function useFormat() {
    // Distance from metres (activity distance, elevation-free routes, etc.).
    function distance(metres, precision = 0) {
        if (metres === null || metres === undefined) {
            return null;
        }
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? metresToKm(metres, precision) : metresToMiles(metres, precision);
        return `${number(converted, precision)} ${km ? 'km' : 'mi'}`;
    }

    // Same, but split so a caller can render the unit inside an <abbr> (StatGrid).
    function distanceParts(metres, precision = 0) {
        if (metres === null || metres === undefined) {
            return null;
        }
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? metresToKm(metres, precision) : metresToMiles(metres, precision);
        return { value: number(converted, precision), unit: km ? 'km' : 'mi' };
    }

    // Distance from a value already in MILES (e.g. flight route.distance).
    function distanceFromMiles(miles, precision = 0) {
        if (miles === null || miles === undefined) {
            return null;
        }
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? milesToKm(miles, precision) : miles;
        return `${number(converted, precision)} ${km ? 'km' : 'mi'}`;
    }

    // Weight from kilograms. 'auto' precision shows 1dp only when fractional,
    // matching the current weightLabel behaviour.
    function weight(kg, precision = 'auto') {
        if (kg === null || kg === undefined) {
            return null;
        }
        const lbs = weightUnitDef.value.value === 'lbs';
        const converted = lbs ? kgToLbs(kg) : Number(kg);
        const digits = precision === 'auto' ? (converted % 1 ? 1 : 0) : precision;
        return `${number(converted, digits)} ${lbs ? 'lbs' : 'kg'}`;
    }

    return {
        distance,
        distanceParts,
        distanceFromMiles,
        weight,
        distanceUnit: distanceUnitDef.value,
        setDistanceUnit: distanceUnitDef.set,
        weightUnit: weightUnitDef.value,
        setWeightUnit: weightUnitDef.set,
    };
}
```

- [ ] **Step 4: Write `SettingToggle.vue`**

Create `resources/js/Components/Layout/SettingToggle.vue` — a segmented radiogroup. Mirror the a11y of `ThemeCards.vue` (role=radio + aria-checked + aria-label + mirrored focus-visible ring):
```vue
<script setup>
const props = defineProps({
    modelValue: { type: String, required: true },
    // [{ value, label }] — the selectable options.
    options: { type: Array, required: true },
    // Visible row label (e.g. "Distance").
    label: { type: String, required: true },
    // Group aria-label for the radiogroup (e.g. "Distance unit").
    ariaLabel: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <span class="text-body text-neutral-900">{{ label }}</span>
        <div
            role="radiogroup"
            :aria-label="ariaLabel"
            class="inline-flex rounded-lg border border-neutral-100 p-0.5"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="modelValue === option.value"
                :aria-label="option.label"
                class="rounded-md px-3 py-1 text-caption font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :class="modelValue === option.value
                    ? 'bg-neutral-900 text-neutral-0'
                    : 'text-neutral-500 hover:text-neutral-900'"
                @click="emit('update:modelValue', option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
```
> Confirm the token classes (`text-body`, `text-caption`, `bg-neutral-900`, `text-neutral-0`, `ring-accent-500`, `border-neutral-100`) match those used in `SettingsModal.vue`/`ThemeCards.vue` from Project A; if a name differs, use the sibling's actual class.

- [ ] **Step 5: Add the Formatting section to `SettingsModal.vue`**

In `resources/js/Components/Layout/SettingsModal.vue`, import the composable + control and add a second `<section>` under the existing Appearance section:
```vue
import SettingToggle from './SettingToggle.vue';
import { useFormat } from '../../composables/useFormat';
```
```vue
const { distanceUnit, setDistanceUnit, weightUnit, setWeightUnit } = useFormat();

// Segmented options for the formatting toggles.
const distanceOptions = [
    { value: 'mi', label: 'mi' },
    { value: 'km', label: 'km' },
];
const weightOptions = [
    { value: 'kg', label: 'kg' },
    { value: 'lbs', label: 'lbs' },
];
```
Add after the Appearance `<section>` (match the existing section's heading markup):
```vue
                        <section class="space-y-3">
                            <h3 class="text-label uppercase tracking-wide text-neutral-500">Formatting</h3>
                            <div class="space-y-3">
                                <SettingToggle
                                    :model-value="distanceUnit"
                                    :options="distanceOptions"
                                    label="Distance"
                                    aria-label="Distance unit"
                                    @update:model-value="setDistanceUnit"
                                />
                                <SettingToggle
                                    :model-value="weightUnit"
                                    :options="weightOptions"
                                    label="Weight"
                                    aria-label="Weight unit"
                                    @update:model-value="setWeightUnit"
                                />
                            </div>
                        </section>
```
> `distanceUnit`/`weightUnit` are refs; Vue unwraps them in the template binding, so `:model-value="distanceUnit"` passes the string reactively.

- [ ] **Step 6: Write the browser test (store + control end-to-end)**

Create `tests/Browser/FormattingSettingsTest.php`. This task's test proves the toggle persists + drives the store, independent of display migration (Task 2 adds the live-display assertions). Mirror `SettingsModalTest.php`'s style:
```php
<?php

it('toggles distance and weight units from the settings modal', function () {
    // Desktop width so the sidebar gear is visible and its aria-labels resolve uniquely.
    $page = visit('/')->resize(1280, 800);

    $page->click('[aria-label="Open settings"]')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Distance: default mi, switch to km, assert persisted + reflected on the control.
    $page->click('[aria-label="Distance unit"] [aria-label="km"]')
        ->assertScript("localStorage.getItem('pref:distanceUnit')", 'km')
        ->assertScript(
            "document.querySelector('[aria-label=\"Distance unit\"] [aria-label=\"km\"]').getAttribute('aria-checked')",
            'true',
        );

    // Weight: default kg, switch to lbs.
    $page->click('[aria-label="Weight unit"] [aria-label="lbs"]')
        ->assertScript("localStorage.getItem('pref:weightUnit')", 'lbs')
        ->assertScript(
            "document.querySelector('[aria-label=\"Weight unit\"] [aria-label=\"lbs\"]').getAttribute('aria-checked')",
            'true',
        );
});
```
> Confirm the `pest-plugin-browser` API against `tests/Browser/SettingsModalTest.php` and mirror it exactly (e.g. `assertScript` signature, `click` selector support for descendant selectors). If descendant selectors aren't supported, give each option a unique `aria-label` (e.g. `"Distance km"`) instead and update `SettingToggle` + this test together. The test MUST pass, not skip.

- [ ] **Step 7: Build + test**

Run: `npm run build` then `php artisan test tests/Browser/FormattingSettingsTest.php --compact`
Expected: build succeeds; test PASSES. (Ignore the unrelated pre-existing `ImportEventsTest`.)

- [ ] **Step 8: Commit** to `feature/settings-panel` (distance.js, format.js, useFormat.js, SettingToggle.vue, SettingsModal.vue, the test).

---

### Task 2: Migrate the distance + weight display call sites to `useFormat`

**Files:**
- Modify: `resources/js/Components/Entry/ActivityDetail.vue` (distance stat + weight labels/volume)
- Modify: `resources/js/Components/Entry/FlightDetail.vue` (distance label)
- Modify: `resources/js/Components/Timeline/FeedItem.vue` (flight note distance, miles-source)
- Test: extend `tests/Browser/FormattingSettingsTest.php` (live display change)

**Interfaces:**
- Consumes: `useFormat()` → `{ distance, distanceParts, distanceFromMiles, weight }` (from Task 1).

- [ ] **Step 1: Migrate `ActivityDetail.vue`**

Replace the `metresToMiles` import (line 9) and add the composable:
```vue
import { useFormat } from '../../composables/useFormat';
```
Remove `import { metresToMiles } from '../../lib/distance.js';`. In `<script setup>`:
```vue
const { distanceParts, weight } = useFormat();
```
Change the Distance stat (currently `{ label: 'Distance', value: number(metresToMiles(props.entry.distance, 1), 1), unit: 'mi' }`) to read the parts reactively:
```vue
    { label: 'Distance', ...(distanceParts(props.entry.distance, 1) ?? { value: null, unit: '' }) },
```
Change `weightLabel` (keep the Bodyweight branch):
```vue
function weightLabel(value) {
    return value > 0 ? weight(value) : 'Bodyweight';
}
```
Change the two volume renders in the template:
- `${number(totalVolume)} kg volume` → `` `${weight(totalVolume, 0)} volume` `` (in the `:meta` binding)
- `{{ number(exercise.volume) }} kg` → `{{ weight(exercise.volume, 0) }}`

> `number` is still used elsewhere in this file (calories, HR) — keep the `number` import. `stats` is a computed and `weightLabel` is called in the template, so both re-run when the units change — live update holds.

- [ ] **Step 2: Migrate `FlightDetail.vue`**

Keep the miles value for the duration estimate (it feeds `flightDurationLabel`, which expects miles), but make the visible label unit-aware. Replace `import { metresToMiles } from '../../lib/distance.js';` with:
```vue
import { metresToMiles } from '../../lib/distance.js';
import { useFormat } from '../../composables/useFormat';
```
Add:
```vue
const { distance } = useFormat();
```
Keep `distanceMiles` (used by `durationLabel`). Change `distanceLabel`:
```vue
const distanceLabel = computed(() => distance(props.entry.distance));
```
(`props.entry.distance` is metres; `distance()` reads the reactive unit, so the `:note` updates live. `distanceMiles` stays for the duration estimate only.)

- [ ] **Step 3: Migrate `FeedItem.vue` flight note (miles source)**

`FeedItem.vue` imports `clock, duration, flightDurationLabel, number` from `lib/format` (line 14) — keep those. Add:
```vue
import { useFormat } from '../../composables/useFormat';
```
Add in `<script setup>`:
```vue
const { distanceFromMiles } = useFormat();
```
Change the flight-route `note` (currently `note: props.route.distance ? \`${number(props.route.distance)} mi\` : null`) — note `route.distance` is in **miles**:
```vue
        note: props.route.distance ? distanceFromMiles(props.route.distance) : null,
```
> The enclosing `routeCard`/flight computed re-runs when `distanceUnit` changes (it calls `distanceFromMiles`), so the note updates live.

- [ ] **Step 4: Extend the browser test with live display assertions**

Add a second test to `tests/Browser/FormattingSettingsTest.php` that seeds an activity with a known distance + gym set, visits its entry, and asserts the rendered display flips live. Mirror the seed/URL pattern from `tests/Feature/EntryViewTest.php` (`$model->occurred_at->format('Y/m/d').'/'.$model->slug()`), inlined here:
```php
use App\Models\Activity;

it('updates rendered distance and weight live when units change', function () {
    // 5000 m -> 3.1 mi / 5.0 km; a 100 kg set -> 220.5 lbs.
    $activity = Activity::factory()->create([
        'name' => 'Units Run',
        'type' => 'weight-training',
        'distance' => 5000,
        'duration' => 1800,
        'occurred_at' => '2026-03-15 07:30:00',
        'meta' => ['sets' => [['exercise' => 'Bench Press', 'reps' => 5, 'weight_kg' => 100]]],
    ]);

    $url = '/'.$activity->occurred_at->format('Y/m/d').'/'.$activity->slug();
    $page = visit($url)->resize(1280, 800);

    // Distance stat unit starts as mi (rendered in a StatGrid <abbr>).
    $page->assertScript(
        "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'mi')",
        true,
    );

    // Open settings and switch distance to km; the <abbr> updates without reload.
    $page->click('[aria-label=\"Open settings\"]')
        ->click('[aria-label=\"Distance unit\"] [aria-label=\"km\"]')
        ->assertScript(
            "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'km')",
            true,
        )
        ->assertScript(
            "[...document.querySelectorAll('abbr')].some(a => a.textContent.trim() === 'mi')",
            false,
        );

    // Switch weight to lbs; the set weight text flips from kg to lbs without reload.
    $page->click('[aria-label=\"Weight unit\"] [aria-label=\"lbs\"]')
        ->assertScript("document.body.innerText.includes('lbs')", true)
        ->assertScript("document.body.innerText.includes(' kg')", false);
});
```
> Verify the exact rendered strings during TDD: run the test, read the failure, and adjust the expected values to what the page actually renders (e.g. whether the set weight shows `220.5 lbs`). The assertions target rendered DOM (`<abbr>` text, `body.innerText`), never props JSON — `entry.distance` is raw metres in props, so `mi`/`km`/`lbs` only appear in rendered output. If `weight-training` isn't a valid activity `type`, use the factory's gym state or a valid strength type (check `ActivityFactory` / the `activity_type_convention`); the assertion targets the rendered set weight regardless of type.

- [ ] **Step 5: Build + test**

Run: `npm run build` then `php artisan test tests/Browser/FormattingSettingsTest.php --compact`
Expected: build succeeds; both tests PASS.

- [ ] **Step 6: Commit** to `feature/settings-panel` (ActivityDetail, FlightDetail, FeedItem, the test).

---

### Task 3: Client-composed card subtitles (timeline cards react to the toggle)

**Context:** Activity/Flight timeline card subtitles are composed server-side into a fused string (`"3.1 mi, 28m, 320 kcal"`, `"5,440 mi, Economy"`, `"3 exercises, 12 sets, 2,450 kg"`) and delivered as `FeedItem`'s `meta` string prop, so the client toggle can't reach the distance/weight in them. Fix: the server emits **structured subtitle tokens** (distance/weight as raw values, everything else as literal text) and the client composes the subtitle reactively via `useFormat`.

**Files:**
- Modify: `app/Models/Activity.php` (`cardSubtitle()`/`strengthSubtitle()` → also emit tokens; `card()` returns `subtitleTokens`)
- Modify: `app/Models/Flight.php` (`card()` returns `subtitleTokens`)
- Modify: `app/Actions/BuildTimelineFeed.php` (`cardItem()` passes `metaTokens`)
- Modify: `resources/js/Components/Timeline/FeedItem.vue` (compose subtitle from tokens reactively)
- Test: `tests/Browser/FormattingSettingsTest.php` (add a timeline-card case)

**Token contract:** an ordered array; each token is one of:
- `['t' => 'dist', 'm' => <int metres>, 'p' => <int precision>]`
- `['t' => 'wt', 'kg' => <number>, 'p' => <int precision>]`
- `['t' => 'text', 'v' => '<literal>']`
Client renders `dist`→`distance(m, p)`, `wt`→`weight(kg, p)`, `text`→`v` verbatim, joined with `', '`.

**Interfaces:**
- Consumes: `useFormat()` → `distance`, `weight` (Task 1). `Distance::miles`/`Distance::km` are NOT used here — the server sends raw metres, the client converts.
- Produces: `card()['subtitleTokens']` (array of tokens, or null); `FeedItem` prop `metaTokens: Array|null`.

- [ ] **Step 1: Emit tokens from `Activity::card()`**

In `app/Models/Activity.php`, add a `subtitleTokens()` method mirroring the existing `cardSubtitle()` logic but returning the token array, and expose it in `card()` as `'subtitleTokens' => $this->subtitleTokens()`. Keep `cardSubtitle()` (the string) as a fallback. Cardio branch:
```php
private function subtitleTokens(): ?array
{
    $isCardio = in_array($this->type, ['run', 'cycle', 'ride', 'swim', 'walk', 'hike']);

    if (! $isCardio && is_array($this->meta['sets'] ?? null)) {
        return $this->strengthTokens($this->meta['sets']);
    }

    $tokens = [];

    if ($isCardio && $this->distance) {
        $tokens[] = ['t' => 'dist', 'm' => (int) $this->distance, 'p' => 1];
    }

    if ($this->duration) {
        $tokens[] = ['t' => 'text', 'v' => $this->durationForHumans($this->duration)];
    }

    if ($this->calories) {
        $tokens[] = ['t' => 'text', 'v' => number_format($this->calories).' kcal'];
    }

    return $tokens ?: null;
}

/**
 * @param  array<int, array{exercise: string, reps: int, weight: float}>  $sets
 * @return array<int, array<string, mixed>>
 */
private function strengthTokens(array $sets): array
{
    $exercises = count(array_unique(array_column($sets, 'exercise')));
    $volume = array_sum(array_map(fn (array $set): float => ($set['reps'] ?? 0) * ($set['weight_kg'] ?? $set['weight'] ?? 0), $sets));

    $tokens = [
        ['t' => 'text', 'v' => $exercises.' '.Str::plural('exercise', $exercises)],
        ['t' => 'text', 'v' => count($sets).' '.Str::plural('set', count($sets))],
    ];

    if ($volume > 0) {
        $tokens[] = ['t' => 'wt', 'kg' => $volume, 'p' => 0];
    }

    return $tokens;
}
```
> `cardSubtitle()`/`strengthSubtitle()` stay as-is (string fallback). This adds a parallel token builder; the duplication is intentional (fallback + tokens) and small.

- [ ] **Step 2: Emit tokens from `Flight::card()`**

In `app/Models/Flight.php`, add `'subtitleTokens'` to the `card()` return, built from raw metres:
```php
'subtitleTokens' => $this->distance
    ? [['t' => 'dist', 'm' => (int) $this->distance, 'p' => 0], ['t' => 'text', 'v' => $this->cabin_class]]
    : null,
```
(Keep the existing `'subtitle'` string as the fallback.)

- [ ] **Step 3: Pass tokens through `BuildTimelineFeed`**

In `app/Actions/BuildTimelineFeed.php` `cardItem()` (which currently sets `'meta' => Text::excerpt($card['subtitle'], 240)`), add:
```php
'metaTokens' => $card['subtitleTokens'] ?? null,
```
(Keep the existing `meta` string key as the fallback for cards without tokens, e.g. notes.)

- [ ] **Step 4: Compose the subtitle from tokens in `FeedItem.vue`**

In `resources/js/Components/Timeline/FeedItem.vue`: add `metaTokens: { type: Array, default: null }` to `defineProps`. Extend the existing `useFormat()` destructure (currently `const { distanceFromMiles } = useFormat();`) to `const { distance, weight, distanceFromMiles } = useFormat();`. Add a computed that composes the subtitle reactively, falling back to the plain `meta` string:
```js
// Timeline card subtitle. When the server sends structured tokens, compose them
// through useFormat so distance/weight react to the unit toggle; otherwise fall
// back to the plain server string (e.g. notes have no unit-bearing subtitle).
const metaText = computed(() => {
    if (!props.metaTokens) {
        return props.meta;
    }
    return props.metaTokens
        .map((token) => {
            if (token.t === 'dist') {
                return distance(token.m, token.p);
            }
            if (token.t === 'wt') {
                return weight(token.kg, token.p);
            }
            return token.v;
        })
        .filter(Boolean)
        .join(', ');
});
```
Render `metaText` where the raw `meta` was rendered (the `<p v-else-if="meta">{{ meta }}</p>` line): change to `<p v-else-if="metaText">{{ metaText }}</p>`.

- [ ] **Step 5: Add a timeline-card browser test**

Add a test to `tests/Browser/FormattingSettingsTest.php` that seeds a cardio activity (with distance) and visits the day page that renders the timeline feed, then toggles units and asserts the rendered card subtitle flips. Use the `Day.vue` page for a seeded date (it renders `TimelineFeed`→`FeedItem`):
```php
it('reformats timeline card subtitles live when distance unit changes', function () {
    Activity::factory()->create([
        'name' => 'Card Run',
        'type' => 'run',
        'distance' => 5000, // 3.1 mi / 5.0 km
        'duration' => 1800,
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    // The day page renders that day's timeline feed (FeedItem cards).
    $page = visit('/2026/03/15')->resize(1280, 800);

    // Card subtitle starts in miles.
    $page->assertScript("document.body.innerText.includes('mi')", true);

    $page->click('[aria-label=\"Open settings\"]')
        ->click('[aria-label=\"Distance unit\"] [aria-label=\"km\"]')
        ->assertScript("document.body.innerText.includes('km')", true);
});
```
> Verify the exact day-page URL/route and that the seeded activity renders a `FeedItem` there (check `Day.vue` + `TimelineController::day`). During TDD, confirm the rendered subtitle string and tighten the assertion if `'mi'`/`'km'` risks a false match elsewhere on the page (e.g. scope to the FeedItem `<p>` via a selector). Assert rendered DOM, not props JSON — `metaTokens` in props holds raw metres, not the `mi`/`km` string.

- [ ] **Step 6: Build + test + commit**

Run `vendor/bin/pint --dirty --format agent`, then `npm run build`, then `php artisan test tests/Browser/FormattingSettingsTest.php --compact` (all pass). Commit to `feature/settings-panel` (Activity.php, Flight.php, BuildTimelineFeed.php, FeedItem.vue, the test).

---

### Task 4: Stats page + timeline aggregates react to the toggle

**Context:** Distance appears in two client shapes here: (a) **split value+unit** stats rendered by `StatGrid.vue` (timeline year/month/day via `TimelineController`) and `MetricCard.vue` (Stats metrics), and (b) **fused strings** (Stats `perWeek` averages `item.display`, `records` `record.value`). Make the split components distance-unit-aware from raw metres, and restructure the fused Stats strings to carry raw metres. Sparkline series (`StatsController::series`) are unit-agnostic trend shapes and stay as-is (out of scope).

**Files:**
- Modify: `resources/js/Components/Stats/StatGrid.vue` (distance-aware stat: `distanceM` + `precision`)
- Modify: `resources/js/Components/Stats/MetricCard.vue` (distance-aware value)
- Modify: `resources/js/Pages/Stats.vue` (perWeek + records render via `useFormat`)
- Modify: `app/Http/Controllers/TimelineController.php` (`periodStats`/`dayStats` send `distanceM` not pre-formatted value+unit)
- Modify: `app/Http/Controllers/StatsController.php` (`metrics`/`averages`/`records` send raw metres)
- Test: `tests/Browser/FormattingSettingsTest.php` (add a Stats + a year-aggregate case)

**Interfaces:**
- `StatGrid` stat shape gains optional `distanceM: number|null` + `precision: number` — when `distanceM != null`, the component derives `value`/`unit` via `useFormat().distanceParts(distanceM, precision)` instead of the static `value`/`unit`. Existing `{ label, value, unit }` and `{ label, seconds }` stats are unchanged.
- `MetricCard` gains the same optional `distanceM` + `precision` props.

- [ ] **Step 1: Make `StatGrid.vue` distance-aware**

In `resources/js/Components/Stats/StatGrid.vue`, import `useFormat` and resolve each stat's display so a `distanceM` stat formats reactively:
```js
import { useFormat } from '../../composables/useFormat';
```
```js
const { distanceParts } = useFormat();

// Resolve each visible stat's value/unit; a stat carrying raw `distanceM`
// formats through the unit toggle, others use their static value/unit.
const resolved = computed(() => visible.value.map((stat) => {
    if (stat.distanceM !== null && stat.distanceM !== undefined) {
        const parts = distanceParts(stat.distanceM, stat.precision ?? 0);
        return { ...stat, value: parts.value, unit: parts.unit };
    }
    return stat;
}));
```
Change the template `v-for` to iterate `resolved` instead of `visible`, keeping the existing `<Duration>`/value+`<abbr>` rendering. (Ensure the `visible` empty-filter still applies — resolve after filtering, as above.)

- [ ] **Step 2: Make `MetricCard.vue` distance-aware**

In `resources/js/Components/Stats/MetricCard.vue`, add props `distanceM: { type: Number, default: null }` and `precision: { type: Number, default: 0 }`, import `useFormat`, and compute the displayed value/unit:
```js
import { useFormat } from '../../composables/useFormat';
```
```js
const { distanceParts } = useFormat();

// When given raw metres, format through the unit toggle; else use the passed value/unit.
const display = computed(() => {
    if (props.distanceM !== null) {
        return distanceParts(props.distanceM, props.precision);
    }
    return { value: props.value, unit: props.unit };
});
```
Render `display.value.value` + `display.value.unit` where `value`/`unit` were used (`{{ value }}<abbr>{{ unit }}</abbr>`).

- [ ] **Step 3: Send raw metres from `TimelineController`**

In `app/Http/Controllers/TimelineController.php`, change the three distance stat builders to send `distanceM` (raw metres) with `precision`, instead of pre-converting to miles:
- `periodStats()` disciplines (~:286-289): replace the `Distance::miles(...)` value with the raw summed metres:
```php
$distanceM = (int) $between(Activity::query())->whereIn('type', $types)->sum('distance');
// ...
['label' => $label, 'distanceM' => $distanceM, 'precision' => 0],
```
- `periodStats()` superlative longest run (~:338-341): `['label' => 'Longest run', 'distanceM' => (int) $between(Activity::query())->where('type', 'run')->max('distance'), 'precision' => 1]`.
- `dayStats()` (~:396-399): `['label' => 'Distance', 'distanceM' => (int) round($activities->sum('distance')), 'precision' => 1]`.
> Drop the now-unused `Distance::miles` local closures where they were only for these. Keep any non-distance stats untouched. `StatGrid` (Step 1) formats `distanceM` reactively.

- [ ] **Step 4: Send raw metres from `StatsController`**

In `app/Http/Controllers/StatsController.php`:
- `metrics()` (~:256-275): each distance metric sends `distanceM` (raw metres, e.g. `$totals['walk_m']`) + `precision` instead of the pre-formatted `value` + `'mi'` unit; pass those to `MetricCard` (Step 5). Keep non-distance metrics unchanged.
- `averages()` (~:286-292): the `Distance` average `perWeek` item sends raw metres + precision. The averaging divides a metres sum by `$divisor`, so send `['label' => 'Distance', 'distanceM' => (int) round(($totals['walk_m'] + $totals['run_m'] + $totals['ride_m']) / $divisor), 'precision' => 1]` (average metres, formatted client-side).
- `records()` (~:375-376): longest run/ride send `['label' => 'Longest run', 'distanceM' => (int) $longestRun, 'precision' => 1]` etc.

- [ ] **Step 5: Render the raw-metres Stats in `Stats.vue`**

In `resources/js/Pages/Stats.vue`, import `useFormat` and render `distanceM`-bearing items through it:
```js
import { useFormat } from '../composables/useFormat';
const { distance } = useFormat();
```
- Metrics `v-for` → pass `:distance-m="metric.distanceM ?? null"` and `:precision="metric.precision ?? 0"` to `<MetricCard>` (alongside the existing `:value`/`:unit` for non-distance metrics).
- `perWeek` `v-for` (currently `{{ item.display }}`): render `{{ item.distanceM != null ? distance(item.distanceM, item.precision) : item.display }}`.
- `records` `v-for` (currently `{{ record.value }}`): render `{{ record.distanceM != null ? distance(record.distanceM, record.precision) : record.value }}`.

- [ ] **Step 6: Add Stats + aggregate browser tests**

Add tests to `tests/Browser/FormattingSettingsTest.php`: seed run/ride activities, visit the year page (renders `StatGrid` aggregates) and the Stats page, toggle to km, and assert a rendered distance stat flips `mi`→`km`. Verify the exact routes (`/2026` year page; the Stats page route via `route:list`/`Stats.vue`), assert rendered `<abbr>`/text (not props JSON), and tighten selectors to avoid false `mi`/`km` matches. Tests must PASS, not skip.

- [ ] **Step 7: Build + test + commit**

Run `vendor/bin/pint --dirty --format agent`, then `npm run build`, then the feature test file (all pass). Commit to `feature/settings-panel` (StatGrid, MetricCard, Stats.vue, TimelineController, StatsController, the test).

---

## Self-Review

**Spec coverage:**
- `distanceUnit` + `weightUnit` via `defineSetting` → Task 1 Step 3. ✓
- Reactive `useFormat()` formatters reading `settingRef.value` in-body (live update) → Task 1 Step 3 + reactivity rule. ✓
- Pure primitives `metresToKm`/`milesToKm`/`kgToLbs` → Task 1 Steps 1-2. ✓
- Formatting section in `SettingsModal` with segmented toggles → Task 1 Steps 4-5. ✓
- Call-site migration (distance metres + miles sources, weight kg) with source-unit care → Task 2 Steps 1-3. ✓
- Live-update browser test asserting rendered DOM (not props JSON) → Task 1 Step 6 + Task 2 Step 4. ✓
- Time/date NOT implemented (deferred B.2) → constraint honoured; no `timeFormat`/`dateFormat`, `clock`/`useClock` untouched. ✓
- `km` already in `UNIT_TITLES` (StatGrid `<abbr>` expansion works); no units.js change needed. ✓

**Placeholder scan:** The token-class and `pest-plugin-browser` API confirmations (against Project A's shipped components/tests) and the "verify exact rendered strings during TDD" note are genuine per-environment checks with concrete fallbacks, not deferred logic. All component/composable code is complete.

**Type/name consistency:** `useFormat()` returns `{ distance, distanceParts, distanceFromMiles, weight, distanceUnit, setDistanceUnit, weightUnit, setWeightUnit }`, used consistently in `SettingsModal` (refs + setters), `ActivityDetail` (`distanceParts`, `weight`), `FlightDetail` (`distance`), `FeedItem` (`distanceFromMiles`). `distanceParts` returns `{ value, unit }` matching StatGrid's `{ value, unit }` stat shape. `weight(kg, 'auto'|0)` precision contract is consistent between `weightLabel` (auto) and volume (0). Settings keys `distanceUnit`/`weightUnit` → localStorage `pref:distanceUnit`/`pref:weightUnit` (defineSetting's `pref:` prefix) match the test's `assertScript` keys.
