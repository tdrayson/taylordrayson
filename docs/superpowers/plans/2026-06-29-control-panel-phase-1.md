# Control Panel Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the foundation of the `/cp` control panel: single-user session auth, a context-aware site shell, a config-driven generic CRUD layer for all timeline and reference models, reusable dual-mode field components, and an Edit affordance on entry pages.

**Architecture:** A `CpResource` class per model derives its form fields from the model's fillable columns + casts + database schema (overriding only special cases), exposed through a `ResourceRegistry`. One generic `ResourceController` drives all CRUD. The Vue side renders forms from the serialized field definitions via a `FieldRenderer` that dispatches to dual-mode (`display`/`edit`) field components wrapping existing `Ui/` primitives. The existing `AppLayout` gains a `cp` mode (collections sidebar + account topbar) while the logged-out public site stays identical.

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia v3, Vue 3, Tailwind v4, Pest 4. Existing helpers: `resources/js/lib/cn.js` (clsx + tailwind-merge), `app/Timeline/TypeRegistry.php`, `Ui/` component library.

## Global Constraints

- No em dashes anywhere (chat, code, comments, UI copy, commit messages). Use commas, periods, colons, parentheses, or "and"/"but". For empty/placeholder display text use the words "Not set", never a dash glyph.
- No arbitrary Tailwind bracket values (`[11px]`, `[58%]`, raw hex). Use the standard scale and existing theme tokens (`accent-500`, `neutral-25`, `text-meta`, etc.). Reuse existing `Ui/` primitives rather than re-styling.
- PHP: always curly braces; explicit return types on every method; constructor property promotion; PHPDoc blocks with `@param`/`@return` and array-shape types where applicable; prefer PHPDoc over inline comments. No abbreviations in variable, key, or method names (spell out fully).
- Run `vendor/bin/pint --dirty --format agent` after any PHP change before committing.
- Do NOT run dev servers (`php artisan serve`, `npm run dev`, `npm run build`). The user runs their own.
- Database is the source of truth; the control panel reads/writes the database, never the `data/*.csv` seeds. Never blanket `git checkout` a `data/*.csv` file.
- Tests: every change is programmatically tested. Run with `php artisan test --compact --filter=...`.
- Single user only: no registration, roles, or permissions.
- Reuse `TypeRegistry` slugs for timeline `/cp` URLs (`activities`, `flights`, `food`, ...). Reference slugs: `airlines`, `airports`, `fuel-stations`.

---

## File Structure

**Backend (PHP):**
- `app/Console/Commands/Cp/CreateAdminUser.php` , artisan `cp:admin` to create/update the single admin.
- `app/Http/Controllers/Cp/LoginController.php` , session login/logout + login page.
- `app/Http/Controllers/Cp/DashboardController.php` , `/cp` landing with collection counts.
- `app/Http/Controllers/Cp/ResourceController.php` , generic CRUD for any resource.
- `app/Http/Requests/Cp/ResourceRequest.php` , validation resolved from the active resource.
- `app/Cp/FieldGuesser.php` , derive field definitions from a model.
- `app/Cp/CpResource.php` , abstract resource base.
- `app/Cp/TimelineCpResource.php` , intermediate base for timeline resources.
- `app/Cp/ResourceRegistry.php` , slug to resource lookup.
- `app/Cp/Resources/*Resource.php` , 16 resource classes.
- `app/Http/Middleware/HandleInertiaRequests.php` (modify) , share `auth.user` and `cp.nav`.
- `bootstrap/app.php` (modify) , redirect guests to `cp.login`.
- `routes/web.php` (modify) , login + `/cp` route group.

**Frontend (Vue):**
- `resources/js/Components/Fields/FieldRenderer.vue` + `TextField/NumberField/TextareaField/DateField/DateTimeField/BooleanField/SelectField/JsonField.vue`.
- `resources/js/Components/Layout/CpSidebarNav.vue` , grouped collections nav.
- `resources/js/Components/Layout/AccountMenu.vue` , topbar account cluster.
- `resources/js/cpIcons.js` , slug to icon glyph map.
- `resources/js/Pages/Cp/Login.vue`, `Cp/Dashboard.vue`, `Cp/Resource/Index.vue`, `Cp/Resource/Form.vue`.
- `resources/js/Layouts/AppLayout.vue`, `Components/Layout/AppSidebar.vue`, `Components/Layout/AppTopbar.vue` (modify) , `mode` awareness.
- `resources/js/Pages/Entry.vue` (modify) , Edit affordance.

**Tests:**
- `tests/Feature/Cp/CreateAdminUserTest.php`, `AuthTest.php`, `SharedPropsTest.php`, `ResourceCrudTest.php`, `ResourceValidationTest.php`, `FieldGuesserTest.php`, `ResourceRegistryTest.php`, `EntryEditLinkTest.php`.
- `tests/Browser/Cp/ShellTest.php`, `FormFlowTest.php`.

---

### Task 1: Admin user artisan command

**Files:**
- Create: `app/Console/Commands/Cp/CreateAdminUser.php`
- Test: `tests/Feature/Cp/CreateAdminUserTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (fillable: name, email, password; password cast `hashed`).
- Produces: artisan command signature `cp:admin {--email=} {--name=} {--password=}`; creates or updates a `User` by email.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;

it('creates a new admin user from options', function () {
    $this->artisan('cp:admin', [
        '--email' => 'taylor@example.com',
        '--name' => 'Taylor Drayson',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::where('email', 'taylor@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Taylor Drayson');
    expect(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('updates the password of an existing admin user', function () {
    User::factory()->create(['email' => 'taylor@example.com']);

    $this->artisan('cp:admin', [
        '--email' => 'taylor@example.com',
        '--name' => 'Taylor Drayson',
        '--password' => 'new-password',
    ])->assertSuccessful();

    expect(User::where('email', 'taylor@example.com')->count())->toBe(1);
    expect(Hash::check('new-password', User::first()->password))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CreateAdminUserTest`
Expected: FAIL (command `cp:admin` does not exist).

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands\Cp;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    /** @var string */
    protected $signature = 'cp:admin {--email=} {--name=} {--password=}';

    /** @var string */
    protected $description = 'Create or update the single control panel admin user';

    public function handle(): int
    {
        $email = $this->option('email') ?: text('Email address', required: true);
        $name = $this->option('name') ?: text('Name', default: 'Taylor Drayson');
        $plainPassword = $this->option('password') ?: password('Password', required: true);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $plainPassword],
        );

        $this->info("Admin user saved: {$user->email}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CreateAdminUserTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Cp/CreateAdminUser.php tests/Feature/Cp/CreateAdminUserTest.php
git commit -m "feat: add cp:admin command to manage the control panel user"
```

---

### Task 2: Session auth (login, logout, guest redirect)

**Files:**
- Create: `app/Http/Controllers/Cp/LoginController.php`
- Create: `resources/js/Pages/Cp/Login.vue`
- Modify: `routes/web.php` (add login routes + a guarded `/cp` placeholder route)
- Modify: `bootstrap/app.php` (redirect guests to `cp.login`)
- Test: `tests/Feature/Cp/AuthTest.php`

**Interfaces:**
- Consumes: `App\Models\User`.
- Produces: named routes `cp.login` (GET), `cp.login.store` (POST), `cp.logout` (POST), `cp.dashboard` (GET, behind `auth`). Login renders Inertia component `Cp/Login`. Successful login redirects to `cp.dashboard`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;

it('redirects guests from the control panel to the login page', function () {
    $this->get('/cp')->assertRedirect(route('cp.login'));
});

it('shows the login page to guests', function () {
    $this->get('/cp/login')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Cp/Login'));
});

it('logs in with valid credentials', function () {
    User::factory()->create(['email' => 'taylor@example.com', 'password' => 'secret-password']);

    $this->post('/cp/login', ['email' => 'taylor@example.com', 'password' => 'secret-password'])
        ->assertRedirect(route('cp.dashboard'));

    $this->assertAuthenticated();
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'taylor@example.com', 'password' => 'secret-password']);

    $this->from('/cp/login')
        ->post('/cp/login', ['email' => 'taylor@example.com', 'password' => 'wrong'])
        ->assertRedirect('/cp/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/cp/logout')->assertRedirect(route('cp.login'));

    $this->assertGuest();
});

it('allows an authenticated user into the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/cp')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Cp/Dashboard'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AuthTest`
Expected: FAIL (routes do not exist).

- [ ] **Step 3: Write the LoginController**

```php
<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Cp/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('cp.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cp.login');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, add the import `use App\Http\Controllers\Cp\LoginController;` (alphabetical with the other Cp controllers) and append at the end of the file:

```php
Route::get('/cp/login', [LoginController::class, 'create'])->name('cp.login');
Route::post('/cp/login', [LoginController::class, 'store'])->name('cp.login.store');
Route::post('/cp/logout', [LoginController::class, 'destroy'])->name('cp.logout');

Route::middleware('auth')->prefix('cp')->name('cp.')->group(function () {
    Route::get('/', fn () => \Inertia\Inertia::render('Cp/Dashboard', ['collections' => []]))->name('dashboard');
});
```

- [ ] **Step 5: Redirect guests to the control panel login**

In `bootstrap/app.php`, inside the `->withMiddleware(function (Middleware $middleware) { ... })` closure (where `HandleInertiaRequests` is appended), add:

```php
$middleware->redirectGuestsTo(fn () => route('cp.login'));
```

- [ ] **Step 6: Write the login page**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';
import Card from '../../Components/Ui/Card.vue';
import Input from '../../Components/Ui/Input.vue';
import Button from '../../Components/Ui/Button.vue';
import AppHead from '../../Components/AppHead.vue';

const form = useForm({ email: '', password: '' });

function submit() {
    form.post('/cp/login');
}
</script>

<template>
    <AppHead :og="{ title: 'Sign in' }" />
    <div class="flex min-h-dvh items-center justify-center px-6">
        <Card variant="elevated" class="w-full max-w-sm">
            <h1 class="font-display text-section">Sign in</h1>
            <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <label class="text-label font-medium text-neutral-700">Email</label>
                    <Input v-model="form.email" type="email" :invalid="!!form.errors.email" />
                    <p v-if="form.errors.email" class="text-caption text-red-600">{{ form.errors.email }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-label font-medium text-neutral-700">Password</label>
                    <Input v-model="form.password" type="password" :invalid="!!form.errors.password" />
                </div>
                <Button type="submit" variant="primary" :disabled="form.processing">Sign in</Button>
            </form>
        </Card>
    </div>
</template>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=AuthTest`
Expected: PASS (6 passed). If `AppHead` requires a differently shaped `og` prop, pass `{}` and rely on its defaults.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Cp/LoginController.php resources/js/Pages/Cp/Login.vue routes/web.php bootstrap/app.php tests/Feature/Cp/AuthTest.php
git commit -m "feat: session auth for the control panel"
```

---

### Task 3: Share auth user and collections nav via Inertia

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Cp/SharedPropsTest.php`

**Interfaces:**
- Consumes: `App\Cp\ResourceRegistry` does NOT exist yet, so this task hardcodes a minimal nav share keyed by group. (Task 6 will replace the hardcoded nav with the registry; the prop shape stays identical.)
- Produces: shared Inertia props `auth.user` (`{name, email}` or null) and `cp.nav` (array of `{group: string, items: [{label, slug}]}`), present only when authenticated.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;

it('shares a null auth user for guests', function () {
    $this->get('/cp/login')
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});

it('shares the auth user and cp nav when authenticated', function () {
    $this->actingAs(User::factory()->create(['name' => 'Taylor Drayson', 'email' => 'taylor@example.com']));

    $this->get('/cp')->assertInertia(fn ($page) => $page
        ->where('auth.user.name', 'Taylor Drayson')
        ->where('auth.user.email', 'taylor@example.com')
        ->has('cp.nav'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SharedPropsTest`
Expected: FAIL (`auth` prop missing).

- [ ] **Step 3: Update the share method**

Replace the `share` method body's return array in `app/Http/Middleware/HandleInertiaRequests.php` with:

```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'appUrl' => rtrim((string) config('app.url'), '/'),
        'auth' => [
            'user' => $user ? ['name' => $user->name, 'email' => $user->email] : null,
        ],
        'cp' => [
            'nav' => $user ? $this->controlPanelNav() : [],
        ],
    ];
}

/**
 * Collections navigation for the control panel sidebar, grouped by section.
 *
 * @return array<int, array{group: string, items: array<int, array{label: string, slug: string}>}>
 */
private function controlPanelNav(): array
{
    return [
        ['group' => 'Timeline', 'items' => [
            ['label' => 'Activities', 'slug' => 'activities'],
            ['label' => 'Sleep', 'slug' => 'sleep'],
            ['label' => 'Food', 'slug' => 'food'],
            ['label' => 'Media', 'slug' => 'media'],
            ['label' => 'Events', 'slug' => 'events'],
            ['label' => 'Appearances', 'slug' => 'appearances'],
            ['label' => 'This Week With', 'slug' => 'this-week-with'],
            ['label' => 'Flights', 'slug' => 'flights'],
            ['label' => 'Places', 'slug' => 'places'],
            ['label' => 'Fuel', 'slug' => 'fuel'],
            ['label' => 'Projects', 'slug' => 'projects'],
            ['label' => 'Articles', 'slug' => 'articles'],
            ['label' => 'Notes', 'slug' => 'notes'],
        ]],
        ['group' => 'Reference', 'items' => [
            ['label' => 'Airlines', 'slug' => 'airlines'],
            ['label' => 'Airports', 'slug' => 'airports'],
            ['label' => 'Fuel stations', 'slug' => 'fuel-stations'],
        ]],
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SharedPropsTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Cp/SharedPropsTest.php
git commit -m "feat: share auth user and collections nav with the frontend"
```

---

### Task 4: Context-aware shell (cp mode)

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue`
- Modify: `resources/js/Components/Layout/AppSidebar.vue`
- Modify: `resources/js/Components/Layout/AppTopbar.vue`
- Create: `resources/js/Components/Layout/CpSidebarNav.vue`
- Create: `resources/js/Components/Layout/AccountMenu.vue`
- Create: `resources/js/cpIcons.js`
- Test: `tests/Browser/Cp/ShellTest.php`

**Interfaces:**
- Consumes: shared props `auth.user`, `cp.nav` (Task 3); `SidebarNavItem.vue`; `Ui/Button.vue`.
- Produces: `AppLayout` accepts a `mode` prop (`'public'` default, `'cp'`). In `cp` mode the sidebar renders `CpSidebarNav` and the topbar renders `AccountMenu`. Pages set `cp` mode via `setLayoutProps({ mode: 'cp' })`. Default behaviour (no `mode` set) is byte-identical to today's public site.

- [ ] **Step 1: Write the failing browser test**

```php
<?php

use App\Models\User;

it('renders the public navigation when logged out', function () {
    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Timeline')
        ->assertSee('Now')
        ->assertDontSee('Sign out');
});

it('renders the collections navigation and account menu in the control panel', function () {
    $this->actingAs(User::factory()->create(['name' => 'Taylor Drayson']));

    $page = visit('/cp');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Flights')
        ->assertSee('Reference')
        ->assertSee('Taylor Drayson');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Browser/Cp/ShellTest.php`
Expected: FAIL (cp nav/account menu not rendered; second test fails).

- [ ] **Step 3: Create the slug to icon map**

```js
// resources/js/cpIcons.js
import {
    WorkoutRunIcon, Moon02Icon, Restaurant01Icon, Film01Icon, Ticket01Icon,
    Mic01Icon, PodcastIcon, AirplaneTakeOff01Icon, Location01Icon, PetrolPumpIcon,
    RocketIcon, File01Icon, Note01Icon, Building06Icon, MapPinIcon,
} from '@hugeicons-pro/core-stroke-rounded';

const ICONS = {
    activities: WorkoutRunIcon,
    sleep: Moon02Icon,
    food: Restaurant01Icon,
    media: Film01Icon,
    events: Ticket01Icon,
    appearances: Mic01Icon,
    'this-week-with': PodcastIcon,
    flights: AirplaneTakeOff01Icon,
    places: Location01Icon,
    fuel: PetrolPumpIcon,
    projects: RocketIcon,
    articles: File01Icon,
    notes: Note01Icon,
    airlines: AirplaneTakeOff01Icon,
    airports: MapPinIcon,
    'fuel-stations': Building06Icon,
};

export function cpIcon(slug) {
    return ICONS[slug] ?? Note01Icon;
}
```

If any imported glyph name does not exist in `@hugeicons-pro/core-stroke-rounded`, use the hugeicons MCP `search_icons` tool to find the correct export name and substitute it.

- [ ] **Step 4: Create CpSidebarNav**

```vue
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidebarNavItem from './SidebarNavItem.vue';
import { cpIcon } from '../../cpIcons.js';

const page = usePage();

const groups = computed(() => page.props.cp?.nav ?? []);

function isActive(slug) {
    return page.url.startsWith(`/cp/${slug}`);
}
</script>

<template>
    <nav aria-label="Collections" class="flex flex-col gap-6">
        <div v-for="group in groups" :key="group.group" class="flex flex-col gap-1.5 md:gap-1">
            <p class="px-3 text-eyebrow uppercase text-neutral-500">{{ group.group }}</p>
            <SidebarNavItem
                v-for="item in group.items"
                :key="item.slug"
                :href="`/cp/${item.slug}`"
                :label="item.label"
                :icon="cpIcon(item.slug)"
                :active="isActive(item.slug)"
            />
        </div>
    </nav>
</template>
```

- [ ] **Step 5: Create AccountMenu**

```vue
<script setup>
import { router, usePage } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';

const page = usePage();

function signOut() {
    router.post('/cp/logout');
}
</script>

<template>
    <div class="flex items-center gap-3">
        <span class="text-meta font-medium text-neutral-700">{{ page.props.auth?.user?.name }}</span>
        <Button variant="secondary" size="sm" @click="signOut">Sign out</Button>
    </div>
</template>
```

- [ ] **Step 6: Make AppSidebar mode-aware**

```vue
<script setup>
import ProfileCard from '../Profile/ProfileCard.vue';
import SidebarNav from './SidebarNav.vue';
import StreakBadge from '../Now/StreakBadge.vue';
import CpSidebarNav from './CpSidebarNav.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    mode: { type: String, default: 'public' },
});
</script>

<template>
    <aside class="hidden flex-none flex-col px-6 py-8 md:flex md:sticky md:top-0 md:h-dvh md:w-66 md:overflow-y-auto">
        <template v-if="mode === 'cp'">
            <Link href="/cp" class="mb-6 font-display text-item-title text-neutral-900">Control panel</Link>
            <CpSidebarNav />
        </template>
        <template v-else>
            <ProfileCard class="mb-6" />
            <SidebarNav />
            <div class="mt-auto hidden pt-8 md:block">
                <StreakBadge />
            </div>
        </template>
    </aside>
</template>
```

- [ ] **Step 7: Make AppTopbar mode-aware**

```vue
<script setup>
import Breadcrumb from './Breadcrumb.vue';
import TimeJump from './TimeJump.vue';
import StatusBar from './StatusBar.vue';
import SearchBar from './SearchBar.vue';
import AccountMenu from './AccountMenu.vue';

defineProps({
    breadcrumb: { type: Array, default: undefined },
    mode: { type: String, default: 'public' },
});
</script>

<template>
    <header class="hidden items-center gap-4 px-5 py-4 md:flex md:px-10">
        <div class="flex min-w-0 flex-1 items-center">
            <Breadcrumb :items="breadcrumb" />
        </div>
        <template v-if="mode === 'cp'">
            <div class="flex flex-1 items-center justify-end">
                <AccountMenu />
            </div>
        </template>
        <template v-else>
            <div class="hidden flex-1 justify-center lg:flex">
                <div class="w-full max-w-sm">
                    <SearchBar />
                </div>
            </div>
            <div class="flex flex-1 items-center justify-end gap-4">
                <div class="hidden sm:block">
                    <StatusBar />
                </div>
                <TimeJump />
            </div>
        </template>
    </header>
</template>
```

- [ ] **Step 8: Thread mode through AppLayout**

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../Components/Layout/AppSidebar.vue';
import AppTopbar from '../Components/Layout/AppTopbar.vue';
import MobileNav from '../Components/Layout/MobileNav.vue';
import MediaPlayer from '../Components/Overlays/MediaPlayer.vue';
import Breadcrumb from '../Components/Layout/Breadcrumb.vue';
import CommandPalette from '../Components/Overlays/CommandPalette.vue';

defineProps({
    breadcrumb: { type: Array, default: () => [] },
    mode: { type: String, default: 'public' },
});

const page = usePage();
</script>

<template>
    <div class="flex min-h-dvh flex-col md:flex-row">
        <AppSidebar :mode="mode" />
        <main class="flex min-w-0 flex-1 flex-col">
            <MobileNav />
            <div class="px-5 py-3 md:hidden">
                <Breadcrumb :items="breadcrumb" />
            </div>
            <AppTopbar :breadcrumb="breadcrumb" :mode="mode" />
            <div :key="page.url" class="content-grid w-full animate-fade-in pb-28 pt-8">
                <slot />
            </div>
        </main>
        <MediaPlayer v-if="mode !== 'cp'" />
        <CommandPalette v-if="mode !== 'cp'" />
    </div>
</template>
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --compact tests/Browser/Cp/ShellTest.php`
Expected: PASS (2 passed). The first test confirms the public site still renders its own nav (unchanged). Note: the `/cp` dashboard page (`Cp/Dashboard.vue`) does not exist until Task 9; for this task add a minimal placeholder so the browser test can load it:

```vue
<!-- resources/js/Pages/Cp/Dashboard.vue (placeholder, replaced in Task 9) -->
<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
setLayoutProps({ mode: 'cp' });
</script>

<template>
    <h1 class="font-display text-display">Control panel</h1>
</template>
```

- [ ] **Step 10: Commit**

```bash
git add resources/js/Layouts/AppLayout.vue resources/js/Components/Layout/AppSidebar.vue resources/js/Components/Layout/AppTopbar.vue resources/js/Components/Layout/CpSidebarNav.vue resources/js/Components/Layout/AccountMenu.vue resources/js/cpIcons.js resources/js/Pages/Cp/Dashboard.vue tests/Browser/Cp/ShellTest.php
git commit -m "feat: context-aware shell with control panel mode"
```

---

### Task 5: Field guesser and resource base classes

**Files:**
- Create: `app/Cp/FieldGuesser.php`
- Create: `app/Cp/CpResource.php`
- Create: `app/Cp/TimelineCpResource.php`
- Test: `tests/Feature/Cp/FieldGuesserTest.php`

**Interfaces:**
- Consumes: any Eloquent model (uses `getFillable()`, `getCasts()`, `getTable()`); `Illuminate\Support\Facades\Schema`.
- Produces:
  - `FieldGuesser::guess(string $model): array<string, array>` , field definitions keyed by column. Each field: `{key, label, type, options, rules, locked, help}`. `type` is one of `text|number|textarea|date|datetime|boolean|select|json`.
  - `abstract CpResource` with: `model(): string`, `slug(): string`, `label(): string`, `pluralLabel(): string`, `group(): string` (default `'Timeline'`), `searchable(): array` (default `[]`), `defaultSort(): array` (default `['id','desc']`), `fieldOverrides(): array` (default `[]`), `fields(): array` (guessed + overridden, returned as a list), `columns(): array` (default first 4 non-textual fields), `rules(): array` (keyed by column), `query(): Builder`, `meta(): array` (serializable for Inertia).
  - `abstract TimelineCpResource extends CpResource` with `defaultSort(): ['occurred_at','desc']`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Cp\FieldGuesser;
use App\Models\Flight;

it('guesses field types from a model', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['occurred_at']['type'])->toBe('datetime');
    expect($fields['duration']['type'])->toBe('number');
    expect($fields['distance_miles']['type'])->toBe('number');
    expect($fields['flight_number']['type'])->toBe('text');
    expect($fields['reason']['type'])->toBe('textarea');
    expect($fields['meta']['type'])->toBe('json');
});

it('marks occurred_at required and other fields nullable', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['occurred_at']['rules'])->toBe(['required', 'date']);
    expect($fields['flight_number']['rules'])->toBe(['nullable', 'string']);
    expect($fields['duration']['rules'])->toBe(['nullable', 'numeric']);
});

it('labels columns as headline text', function () {
    $fields = app(FieldGuesser::class)->guess(Flight::class);

    expect($fields['flight_number']['label'])->toBe('Flight Number');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FieldGuesserTest`
Expected: FAIL (class does not exist).

- [ ] **Step 3: Write FieldGuesser**

```php
<?php

namespace App\Cp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FieldGuesser
{
    /** @var array<int, string> Columns whose contents are long-form prose. */
    private const LONG_TEXT = [
        'description', 'long_description', 'notes', 'excerpt',
        'transcript', 'show_notes', 'address', 'reason',
    ];

    /**
     * Derive a field definition for every fillable column on a model.
     *
     * @param  class-string<Model>  $model
     * @return array<string, array{key: string, label: string, type: string, options: array<int, mixed>, rules: array<int, string>, locked: bool, help: ?string}>
     */
    public function guess(string $model): array
    {
        $instance = new $model;
        $casts = $instance->getCasts();
        $table = $instance->getTable();

        $fields = [];

        foreach ($instance->getFillable() as $column) {
            $type = $this->typeFor($column, $casts[$column] ?? null, $table);

            $fields[$column] = [
                'key' => $column,
                'label' => Str::headline($column),
                'type' => $type,
                'options' => [],
                'rules' => $this->rulesFor($column, $type),
                'locked' => false,
                'help' => null,
            ];
        }

        return $fields;
    }

    private function typeFor(string $column, ?string $cast, string $table): string
    {
        $schemaType = Schema::hasColumn($table, $column) ? Schema::getColumnType($table, $column) : 'string';

        return match (true) {
            $column === 'occurred_at', $cast === 'datetime', in_array($schemaType, ['datetime', 'timestamp'], true) => 'datetime',
            $schemaType === 'date' => 'date',
            $cast === 'boolean', $schemaType === 'boolean' => 'boolean',
            $cast === 'array', $schemaType === 'json' => 'json',
            $this->isNumeric($cast, $schemaType) => 'number',
            in_array($column, self::LONG_TEXT, true), $schemaType === 'text' => 'textarea',
            default => 'text',
        };
    }

    private function isNumeric(?string $cast, string $schemaType): bool
    {
        if (in_array($cast, ['integer', 'float', 'double'], true) || Str::startsWith((string) $cast, 'decimal')) {
            return true;
        }

        return in_array($schemaType, ['integer', 'bigint', 'smallint', 'decimal', 'float', 'double'], true);
    }

    /**
     * @return array<int, string>
     */
    private function rulesFor(string $column, string $type): array
    {
        if ($column === 'occurred_at') {
            return ['required', 'date'];
        }

        return match ($type) {
            'datetime', 'date' => ['nullable', 'date'],
            'boolean' => ['boolean'],
            'number' => ['nullable', 'numeric'],
            'json' => ['nullable', 'json'],
            default => ['nullable', 'string'],
        };
    }
}
```

- [ ] **Step 4: Write CpResource and TimelineCpResource**

```php
<?php

namespace App\Cp;

use Illuminate\Contracts\Database\Eloquent\Builder;

abstract class CpResource
{
    /** @return class-string */
    abstract public function model(): string;

    abstract public function slug(): string;

    abstract public function label(): string;

    abstract public function pluralLabel(): string;

    public function group(): string
    {
        return 'Timeline';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return [];
    }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Partial field definitions merged over the guessed fields, keyed by column.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fieldOverrides(): array
    {
        return [];
    }

    /**
     * The resolved field definitions: guessed from the model, then overridden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $guessed = app(FieldGuesser::class)->guess($this->model());

        foreach ($this->fieldOverrides() as $column => $override) {
            $guessed[$column] = array_merge($guessed[$column] ?? ['key' => $column, 'label' => $column], $override);
        }

        return array_values($guessed);
    }

    /**
     * Index table columns. Defaults to the first four non-textual fields.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function columns(): array
    {
        return collect($this->fields())
            ->reject(fn (array $field): bool => in_array($field['type'], ['textarea', 'json'], true))
            ->take(4)
            ->map(fn (array $field): array => ['key' => $field['key'], 'label' => $field['label']])
            ->values()
            ->all();
    }

    /**
     * Validation rules keyed by column, derived from the resolved fields.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (array $field): array => [$field['key'] => $field['rules']])
            ->all();
    }

    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /**
     * The serializable metadata sent to the frontend.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'slug' => $this->slug(),
            'label' => $this->label(),
            'pluralLabel' => $this->pluralLabel(),
            'group' => $this->group(),
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'searchable' => $this->searchable(),
        ];
    }
}
```

```php
<?php

namespace App\Cp;

abstract class TimelineCpResource extends CpResource
{
    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return ['occurred_at', 'desc'];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=FieldGuesserTest`
Expected: PASS (3 passed).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Cp/FieldGuesser.php app/Cp/CpResource.php app/Cp/TimelineCpResource.php tests/Feature/Cp/FieldGuesserTest.php
git commit -m "feat: field guesser and control panel resource base"
```

---

### Task 6: Resource registry and the 16 resource classes

**Files:**
- Create: `app/Cp/ResourceRegistry.php`
- Create: `app/Cp/Resources/ActivityResource.php`, `SleepResource.php`, `CalorieResource.php`, `MediaResource.php`, `EventResource.php`, `AppearanceResource.php`, `PodcastResource.php`, `FlightResource.php`, `CheckinResource.php`, `FuelResource.php`, `ProjectResource.php`, `ArticleResource.php`, `NoteResource.php`, `AirlineResource.php`, `AirportResource.php`, `FuelStationResource.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (replace hardcoded `controlPanelNav` with the registry)
- Test: `tests/Feature/Cp/ResourceRegistryTest.php`

**Interfaces:**
- Consumes: `CpResource`, `TimelineCpResource`; the models in `App\Models`.
- Produces: `ResourceRegistry::all(): array<string, CpResource>` keyed by slug; `ResourceRegistry::find(string $slug): ?CpResource`; `ResourceRegistry::nav(): array` (grouped `{group, items:[{label, slug}]}` for the sidebar share).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Cp\Resources\FlightResource;
use App\Cp\ResourceRegistry;

it('resolves resources by slug', function () {
    $registry = app(ResourceRegistry::class);

    expect($registry->find('flights'))->toBeInstanceOf(FlightResource::class);
    expect($registry->find('nope'))->toBeNull();
});

it('registers all sixteen resources', function () {
    expect(app(ResourceRegistry::class)->all())->toHaveCount(16);
});

it('builds grouped navigation', function () {
    $nav = app(ResourceRegistry::class)->nav();

    $groups = collect($nav)->pluck('group');

    expect($groups)->toContain('Timeline');
    expect($groups)->toContain('Reference');
});

it('applies the cabin class select override on flights', function () {
    $fields = collect(app(ResourceRegistry::class)->find('flights')->fields());
    $cabin = $fields->firstWhere('key', 'cabin_class');

    expect($cabin['type'])->toBe('select');
    expect($cabin['options'])->toContain(['value' => 'business', 'label' => 'Business']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ResourceRegistryTest`
Expected: FAIL (registry/resources do not exist).

- [ ] **Step 3: Write the timeline resource classes**

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Activity;

class ActivityResource extends TimelineCpResource
{
    public function model(): string { return Activity::class; }
    public function slug(): string { return 'activities'; }
    public function label(): string { return 'Activity'; }
    public function pluralLabel(): string { return 'Activities'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['name', 'type']; }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'duration', 'label' => 'Duration'],
        ];
    }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Sleep;

class SleepResource extends TimelineCpResource
{
    public function model(): string { return Sleep::class; }
    public function slug(): string { return 'sleep'; }
    public function label(): string { return 'Sleep'; }
    public function pluralLabel(): string { return 'Sleep'; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Calorie;

class CalorieResource extends TimelineCpResource
{
    public function model(): string { return Calorie::class; }
    public function slug(): string { return 'food'; }
    public function label(): string { return 'Food entry'; }
    public function pluralLabel(): string { return 'Food'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['name', 'meal']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Media;

class MediaResource extends TimelineCpResource
{
    public function model(): string { return Media::class; }
    public function slug(): string { return 'media'; }
    public function label(): string { return 'Media'; }
    public function pluralLabel(): string { return 'Media'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['title', 'type']; }

    /** @return array<string, array<string, mixed>> */
    public function fieldOverrides(): array
    {
        return [
            'type' => ['type' => 'select', 'options' => [
                ['value' => 'film', 'label' => 'Film'],
                ['value' => 'tv', 'label' => 'TV'],
                ['value' => 'tv_episode', 'label' => 'TV episode'],
                ['value' => 'book', 'label' => 'Book'],
            ]],
        ];
    }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Event;

class EventResource extends TimelineCpResource
{
    public function model(): string { return Event::class; }
    public function slug(): string { return 'events'; }
    public function label(): string { return 'Event'; }
    public function pluralLabel(): string { return 'Events'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['name', 'venue_name', 'city']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Appearance;

class AppearanceResource extends TimelineCpResource
{
    public function model(): string { return Appearance::class; }
    public function slug(): string { return 'appearances'; }
    public function label(): string { return 'Appearance'; }
    public function pluralLabel(): string { return 'Appearances'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['title', 'show_name']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Podcast;

class PodcastResource extends TimelineCpResource
{
    public function model(): string { return Podcast::class; }
    public function slug(): string { return 'this-week-with'; }
    public function label(): string { return 'Episode'; }
    public function pluralLabel(): string { return 'This Week With'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['topic']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Flight;

class FlightResource extends TimelineCpResource
{
    public function model(): string { return Flight::class; }
    public function slug(): string { return 'flights'; }
    public function label(): string { return 'Flight'; }
    public function pluralLabel(): string { return 'Flights'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['flight_number', 'origin_iata', 'destination_iata', 'reason']; }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Date'],
            ['key' => 'flight_number', 'label' => 'Flight'],
            ['key' => 'origin_iata', 'label' => 'From'],
            ['key' => 'destination_iata', 'label' => 'To'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function fieldOverrides(): array
    {
        return [
            'cabin_class' => ['type' => 'select', 'options' => [
                ['value' => 'economy', 'label' => 'Economy'],
                ['value' => 'premium-economy', 'label' => 'Premium economy'],
                ['value' => 'business', 'label' => 'Business'],
                ['value' => 'first', 'label' => 'First'],
            ]],
        ];
    }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Checkin;

class CheckinResource extends TimelineCpResource
{
    public function model(): string { return Checkin::class; }
    public function slug(): string { return 'places'; }
    public function label(): string { return 'Place'; }
    public function pluralLabel(): string { return 'Places'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['venue_name', 'category', 'city']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Fuel;

class FuelResource extends TimelineCpResource
{
    public function model(): string { return Fuel::class; }
    public function slug(): string { return 'fuel'; }
    public function label(): string { return 'Fuel entry'; }
    public function pluralLabel(): string { return 'Fuel'; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Project;

class ProjectResource extends TimelineCpResource
{
    public function model(): string { return Project::class; }
    public function slug(): string { return 'projects'; }
    public function label(): string { return 'Project'; }
    public function pluralLabel(): string { return 'Projects'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['title', 'status']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Article;

class ArticleResource extends TimelineCpResource
{
    public function model(): string { return Article::class; }
    public function slug(): string { return 'articles'; }
    public function label(): string { return 'Article'; }
    public function pluralLabel(): string { return 'Articles'; }

    /** @return array<int, string> */
    public function searchable(): array { return ['title', 'excerpt']; }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Date'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'draft', 'label' => 'Draft'],
        ];
    }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Note;

class NoteResource extends TimelineCpResource
{
    public function model(): string { return Note::class; }
    public function slug(): string { return 'notes'; }
    public function label(): string { return 'Note'; }
    public function pluralLabel(): string { return 'Notes'; }
}
```

- [ ] **Step 4: Write the reference resource classes**

```php
<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\Airline;

class AirlineResource extends CpResource
{
    public function model(): string { return Airline::class; }
    public function slug(): string { return 'airlines'; }
    public function label(): string { return 'Airline'; }
    public function pluralLabel(): string { return 'Airlines'; }
    public function group(): string { return 'Reference'; }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array { return ['name', 'asc']; }

    /** @return array<int, string> */
    public function searchable(): array { return ['name', 'iata_code', 'icao_code']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\Airport;

class AirportResource extends CpResource
{
    public function model(): string { return Airport::class; }
    public function slug(): string { return 'airports'; }
    public function label(): string { return 'Airport'; }
    public function pluralLabel(): string { return 'Airports'; }
    public function group(): string { return 'Reference'; }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array { return ['name', 'asc']; }

    /** @return array<int, string> */
    public function searchable(): array { return ['name', 'iata_code', 'city']; }
}
```

```php
<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\FuelStation;

class FuelStationResource extends CpResource
{
    public function model(): string { return FuelStation::class; }
    public function slug(): string { return 'fuel-stations'; }
    public function label(): string { return 'Fuel station'; }
    public function pluralLabel(): string { return 'Fuel stations'; }
    public function group(): string { return 'Reference'; }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array { return ['name', 'asc']; }

    /** @return array<int, string> */
    public function searchable(): array { return ['name', 'city']; }
}
```

- [ ] **Step 5: Write the registry**

```php
<?php

namespace App\Cp;

class ResourceRegistry
{
    /** @var array<int, class-string<CpResource>> */
    private const RESOURCES = [
        Resources\ActivityResource::class,
        Resources\SleepResource::class,
        Resources\CalorieResource::class,
        Resources\MediaResource::class,
        Resources\EventResource::class,
        Resources\AppearanceResource::class,
        Resources\PodcastResource::class,
        Resources\FlightResource::class,
        Resources\CheckinResource::class,
        Resources\FuelResource::class,
        Resources\ProjectResource::class,
        Resources\ArticleResource::class,
        Resources\NoteResource::class,
        Resources\AirlineResource::class,
        Resources\AirportResource::class,
        Resources\FuelStationResource::class,
    ];

    /**
     * @return array<string, CpResource>
     */
    public function all(): array
    {
        $resources = [];

        foreach (self::RESOURCES as $class) {
            $resource = new $class;
            $resources[$resource->slug()] = $resource;
        }

        return $resources;
    }

    public function find(string $slug): ?CpResource
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * Grouped navigation for the control panel sidebar.
     *
     * @return array<int, array{group: string, items: array<int, array{label: string, slug: string}>}>
     */
    public function nav(): array
    {
        $grouped = [];

        foreach ($this->all() as $resource) {
            $grouped[$resource->group()][] = ['label' => $resource->pluralLabel(), 'slug' => $resource->slug()];
        }

        return collect($grouped)
            ->map(fn (array $items, string $group): array => ['group' => $group, 'items' => $items])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 6: Replace the hardcoded nav with the registry**

In `app/Http/Middleware/HandleInertiaRequests.php`, delete the `controlPanelNav()` method and change the `cp` share to use the registry:

```php
'cp' => [
    'nav' => $user ? app(\App\Cp\ResourceRegistry::class)->nav() : [],
],
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ResourceRegistryTest`
Expected: PASS (4 passed).

Run: `php artisan test --compact --filter=SharedPropsTest`
Expected: PASS (2 passed, nav still shared).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Cp/ResourceRegistry.php app/Cp/Resources tests/Feature/Cp/ResourceRegistryTest.php app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat: control panel resource registry and resource definitions"
```

---

### Task 7: Generic resource controller, request, and routes

**Files:**
- Create: `app/Http/Requests/Cp/ResourceRequest.php`
- Create: `app/Http/Controllers/Cp/ResourceController.php`
- Modify: `routes/web.php` (add resource routes to the `cp` group)
- Test: `tests/Feature/Cp/ResourceCrudTest.php`, `tests/Feature/Cp/ResourceValidationTest.php`

**Interfaces:**
- Consumes: `ResourceRegistry`, `CpResource`.
- Produces: routes `cp.resource.index` (`GET /cp/{resource}`), `cp.resource.create` (`GET /cp/{resource}/create`), `cp.resource.store` (`POST /cp/{resource}`), `cp.resource.edit` (`GET /cp/{resource}/{id}/edit`), `cp.resource.update` (`PUT /cp/{resource}/{id}`), `cp.resource.destroy` (`DELETE /cp/{resource}/{id}`). Index renders `Cp/Resource/Index` with props `resource` (meta), `records` (paginator), `filters` (`{search, sort, direction}`). Create/edit render `Cp/Resource/Form` with `resource`, `record` (null on create), `values`. Unknown resource slug returns 404.

- [ ] **Step 1: Write the failing CRUD test**

```php
<?php

use App\Models\Flight;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists records for a resource', function () {
    Flight::factory()->count(3)->create();

    $this->get('/cp/flights')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Cp/Resource/Index')
            ->where('resource.slug', 'flights')
            ->has('records.data', 3));
});

it('shows the create form', function () {
    $this->get('/cp/flights/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Cp/Resource/Form')
            ->where('record', null)
            ->has('resource.fields'));
});

it('stores a new record', function () {
    $this->post('/cp/flights', [
        'occurred_at' => '2026-07-01T09:30',
        'flight_number' => 'BA112',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'cabin_class' => 'business',
    ])->assertRedirect('/cp/flights');

    expect(Flight::where('flight_number', 'BA112')->exists())->toBeTrue();
});

it('updates a record', function () {
    $flight = Flight::factory()->create(['flight_number' => 'OLD']);

    $this->put("/cp/flights/{$flight->id}", [
        'occurred_at' => $flight->occurred_at->format('Y-m-d\TH:i'),
        'flight_number' => 'NEW123',
    ])->assertRedirect('/cp/flights');

    expect($flight->fresh()->flight_number)->toBe('NEW123');
});

it('deletes a record', function () {
    $flight = Flight::factory()->create();

    $this->delete("/cp/flights/{$flight->id}")->assertRedirect('/cp/flights');

    expect(Flight::find($flight->id))->toBeNull();
});

it('returns 404 for an unknown resource', function () {
    $this->get('/cp/nonsense')->assertNotFound();
});

it('blocks guests from resource routes', function () {
    auth()->logout();

    $this->get('/cp/flights')->assertRedirect(route('cp.login'));
});
```

- [ ] **Step 2: Write the failing validation test**

```php
<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('requires occurred_at on a timeline resource', function () {
    $this->from('/cp/flights/create')
        ->post('/cp/flights', ['flight_number' => 'BA1'])
        ->assertSessionHasErrors('occurred_at');
});

it('rejects a non-numeric number field', function () {
    $this->from('/cp/flights/create')
        ->post('/cp/flights', ['occurred_at' => '2026-07-01T09:30', 'duration' => 'not-a-number'])
        ->assertSessionHasErrors('duration');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact --filter="ResourceCrudTest|ResourceValidationTest"`
Expected: FAIL (routes do not exist).

- [ ] **Step 4: Write ResourceRequest**

```php
<?php

namespace App\Http\Requests\Cp;

use App\Cp\ResourceRegistry;
use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $resource = app(ResourceRegistry::class)->find((string) $this->route('resource'));

        abort_if($resource === null, 404);

        return $resource->rules();
    }
}
```

- [ ] **Step 5: Write ResourceController**

```php
<?php

namespace App\Http\Controllers\Cp;

use App\Cp\CpResource;
use App\Cp\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\ResourceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(private ResourceRegistry $registry) {}

    public function index(Request $request, string $resource): Response
    {
        $definition = $this->resolve($resource);

        [$sortColumn, $sortDirection] = $definition->defaultSort();
        $sortColumn = (string) $request->string('sort', $sortColumn);
        $sortDirection = $request->string('direction', $sortDirection) === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->string('search'));

        $query = $definition->query();

        if ($search !== '' && $definition->searchable() !== []) {
            $query->where(function ($builder) use ($definition, $search): void {
                foreach ($definition->searchable() as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $records = $query->orderBy($sortColumn, $sortDirection)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Cp/Resource/Index', [
            'resource' => $definition->meta(),
            'records' => $records,
            'filters' => ['search' => $search, 'sort' => $sortColumn, 'direction' => $sortDirection],
        ]);
    }

    public function create(string $resource): Response
    {
        $definition = $this->resolve($resource);

        return Inertia::render('Cp/Resource/Form', [
            'resource' => $definition->meta(),
            'record' => null,
            'values' => $this->emptyValues($definition),
        ]);
    }

    public function store(ResourceRequest $request, string $resource): RedirectResponse
    {
        $definition = $this->resolve($resource);

        ($definition->model())::create($this->prepare($definition, $request->validated()));

        return redirect()->route('cp.resource.index', $resource);
    }

    public function edit(string $resource, int $id): Response
    {
        $definition = $this->resolve($resource);
        $record = $definition->query()->findOrFail($id);

        return Inertia::render('Cp/Resource/Form', [
            'resource' => $definition->meta(),
            'record' => ['id' => $record->getKey()],
            'values' => $this->recordValues($definition, $record),
        ]);
    }

    public function update(ResourceRequest $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->resolve($resource);
        $record = $definition->query()->findOrFail($id);

        $record->update($this->prepare($definition, $request->validated()));

        return redirect()->route('cp.resource.index', $resource);
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        $definition = $this->resolve($resource);
        $definition->query()->findOrFail($id)->delete();

        return redirect()->route('cp.resource.index', $resource);
    }

    private function resolve(string $resource): CpResource
    {
        return $this->registry->find($resource) ?? abort(404);
    }

    /**
     * Cast submitted values for storage (decode JSON fields, coerce booleans).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepare(CpResource $definition, array $validated): array
    {
        foreach ($definition->fields() as $field) {
            $key = $field['key'];

            if (! array_key_exists($key, $validated)) {
                if ($field['type'] === 'boolean') {
                    $validated[$key] = false;
                }

                continue;
            }

            if ($field['type'] === 'json') {
                $validated[$key] = $validated[$key] === null || $validated[$key] === ''
                    ? null
                    : json_decode((string) $validated[$key], true);
            }

            if ($field['type'] === 'boolean') {
                $validated[$key] = (bool) $validated[$key];
            }
        }

        return $validated;
    }

    /**
     * Empty form values keyed by field, suitable for a create form.
     *
     * @return array<string, mixed>
     */
    private function emptyValues(CpResource $definition): array
    {
        $values = [];

        foreach ($definition->fields() as $field) {
            $values[$field['key']] = $field['type'] === 'boolean' ? false : '';
        }

        return $values;
    }

    /**
     * Form values for an existing record, with datetimes and JSON serialized for editing.
     *
     * @return array<string, mixed>
     */
    private function recordValues(CpResource $definition, \Illuminate\Database\Eloquent\Model $record): array
    {
        $values = [];

        foreach ($definition->fields() as $field) {
            $key = $field['key'];
            $value = $record->getAttribute($key);

            $values[$key] = match ($field['type']) {
                'datetime' => $value?->format('Y-m-d\TH:i'),
                'date' => $value?->format('Y-m-d'),
                'json' => $value === null ? '' : json_encode($value, JSON_PRETTY_PRINT),
                'boolean' => (bool) $value,
                default => $value,
            };
        }

        return $values;
    }
}
```

- [ ] **Step 6: Add the resource routes**

In `routes/web.php`, add the import `use App\Http\Controllers\Cp\ResourceController;` and inside the existing `Route::middleware('auth')->prefix('cp')->name('cp.')->group(...)` (alongside the `dashboard` route), add:

```php
Route::get('/{resource}', [ResourceController::class, 'index'])->name('resource.index');
Route::get('/{resource}/create', [ResourceController::class, 'create'])->name('resource.create');
Route::post('/{resource}', [ResourceController::class, 'store'])->name('resource.store');
Route::get('/{resource}/{id}/edit', [ResourceController::class, 'edit'])->where('id', '[0-9]+')->name('resource.edit');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->where('id', '[0-9]+')->name('resource.update');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->where('id', '[0-9]+')->name('resource.destroy');
```

Note: the `/{resource}/create` route must be registered after `/{resource}` is fine because `create` is a literal segment; Laravel matches the more specific literal first only when registered first, so register `create` BEFORE the catch-all `{resource}` index if a conflict arises. To be safe, place the `create` route line above the `index` route line.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact --filter="ResourceCrudTest|ResourceValidationTest"`
Expected: PASS (9 passed). If a model factory is missing for any model used, that is out of scope here; the tests above only use `Flight::factory()`, which exists.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Cp/ResourceRequest.php app/Http/Controllers/Cp/ResourceController.php routes/web.php tests/Feature/Cp/ResourceCrudTest.php tests/Feature/Cp/ResourceValidationTest.php
git commit -m "feat: generic control panel resource CRUD controller"
```

---

### Task 8: Field components and renderer

**Files:**
- Create: `resources/js/Components/Fields/FieldRenderer.vue`, `TextField.vue`, `NumberField.vue`, `TextareaField.vue`, `DateField.vue`, `DateTimeField.vue`, `BooleanField.vue`, `SelectField.vue`, `JsonField.vue`
- Test: covered by Task 9's browser test (`tests/Browser/Cp/FormFlowTest.php`); this task has no standalone test because there is no JS unit harness, so its gate is that the components compile and Task 9's form flow passes.

**Interfaces:**
- Consumes: `Ui/Input.vue`, `Ui/Textarea.vue`, `Ui/Switch.vue`, `Search/StyledSelect.vue`.
- Produces: each field component accepts props `field` (`{key, label, type, options, help}`), `modelValue`, `mode` (`'display'|'edit'`, default `'edit'`), `error` (`String|null`); emits `update:modelValue`. `FieldRenderer` maps `field.type` to a component (fallback `TextField`).

- [ ] **Step 1: Create a shared field wrapper convention**

Every field renders a label, then either a display value or the edit control plus error. Create `TextField.vue`:

```vue
<script setup>
import Input from '../Ui/Input.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="modelValue !== '' && modelValue !== null">{{ modelValue }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <Input
                :model-value="modelValue ?? ''"
                :invalid="!!error"
                @update:model-value="$emit('update:modelValue', $event)"
            />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 2: Create NumberField**

```vue
<script setup>
import Input from '../Ui/Input.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

function onInput(value) {
    emit('update:modelValue', value === '' ? '' : Number(value));
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="modelValue !== '' && modelValue !== null">{{ modelValue }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <Input type="number" :model-value="modelValue ?? ''" :invalid="!!error" @update:model-value="onInput" />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 3: Create TextareaField**

```vue
<script setup>
import Textarea from '../Ui/Textarea.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="whitespace-pre-line text-meta text-neutral-900">
            <span v-if="modelValue">{{ modelValue }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <Textarea :model-value="modelValue ?? ''" :invalid="!!error" @update:model-value="$emit('update:modelValue', $event)" />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 4: Create DateField and DateTimeField**

```vue
<!-- DateField.vue -->
<script setup>
import Input from '../Ui/Input.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="modelValue">{{ modelValue }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <Input type="date" :model-value="modelValue ?? ''" :invalid="!!error" @update:model-value="$emit('update:modelValue', $event)" />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

```vue
<!-- DateTimeField.vue: identical to DateField but type="datetime-local" -->
<script setup>
import Input from '../Ui/Input.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="modelValue">{{ modelValue }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <Input type="datetime-local" :model-value="modelValue ?? ''" :invalid="!!error" @update:model-value="$emit('update:modelValue', $event)" />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 5: Create BooleanField**

```vue
<script setup>
import Switch from '../Ui/Switch.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: Boolean, default: false },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">{{ modelValue ? 'Yes' : 'No' }}</p>
        <Switch v-else :model-value="!!modelValue" @update:model-value="$emit('update:modelValue', $event)" />
    </div>
</template>
```

- [ ] **Step 6: Create SelectField**

```vue
<script setup>
import { computed } from 'vue';
import StyledSelect from '../Search/StyledSelect.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const displayLabel = computed(() => {
    const match = (props.field.options ?? []).find((option) => option.value === props.modelValue);

    return match ? match.label : null;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="displayLabel">{{ displayLabel }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <StyledSelect
                :model-value="modelValue ?? ''"
                :options="field.options ?? []"
                placeholder="Select"
                @update:model-value="$emit('update:modelValue', $event)"
            />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 7: Create JsonField**

```vue
<script setup>
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Object, Array], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <pre v-if="mode === 'display'" class="overflow-x-auto rounded-md bg-neutral-25 p-3 text-caption text-neutral-700">{{ modelValue || 'Not set' }}</pre>
        <template v-else>
            <textarea
                :value="modelValue ?? ''"
                rows="6"
                class="w-full rounded-md border bg-neutral-0 px-3 py-2 font-mono text-caption text-neutral-900 transition-colors focus:outline-none"
                :class="error ? 'border-red-500 focus:border-red-500' : 'border-neutral-100 focus:border-accent-500'"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
```

- [ ] **Step 8: Create FieldRenderer**

```vue
<script setup>
import { computed } from 'vue';
import TextField from './TextField.vue';
import NumberField from './NumberField.vue';
import TextareaField from './TextareaField.vue';
import DateField from './DateField.vue';
import DateTimeField from './DateTimeField.vue';
import BooleanField from './BooleanField.vue';
import SelectField from './SelectField.vue';
import JsonField from './JsonField.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const COMPONENTS = {
    text: TextField,
    number: NumberField,
    textarea: TextareaField,
    date: DateField,
    datetime: DateTimeField,
    boolean: BooleanField,
    select: SelectField,
    json: JsonField,
};

const component = computed(() => COMPONENTS[props.field.type] ?? TextField);
</script>

<template>
    <component
        :is="component"
        :field="field"
        :mode="mode"
        :error="error"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
```

- [ ] **Step 9: Commit**

```bash
git add resources/js/Components/Fields
git commit -m "feat: dual-mode field components and renderer"
```

---

### Task 9: Control panel pages (dashboard, index, form)

**Files:**
- Create: `resources/js/Pages/Cp/Resource/Index.vue`, `resources/js/Pages/Cp/Resource/Form.vue`
- Replace: `resources/js/Pages/Cp/Dashboard.vue` (the Task 4 placeholder)
- Modify: `routes/web.php` (point `/cp` at a `DashboardController`)
- Create: `app/Http/Controllers/Cp/DashboardController.php`
- Test: `tests/Browser/Cp/FormFlowTest.php`

**Interfaces:**
- Consumes: `AppLayout` (cp mode), `FieldRenderer`, `Ui/Button`, `Ui/Input`, `Ui/Card`; controller props from Task 7.
- Produces: working create/edit/list/delete UI. `DashboardController` renders `Cp/Dashboard` with `collections` (`[{group, items:[{label, slug, count}]}]`).

- [ ] **Step 1: Write the failing browser test**

```php
<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a flight through the form', function () {
    $page = visit('/cp/flights/create');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Flight Number')
        ->fill('occurred_at', '2026-07-01T09:30')
        ->fill('flight_number', 'BA112')
        ->fill('origin_iata', 'LHR')
        ->fill('destination_iata', 'JFK')
        ->click('Save');

    $page->assertPathIs('/cp/flights')
        ->assertSee('BA112');
});
```

Note: the `fill()` selectors target inputs by their `name` attribute. Ensure each edit control in the field components binds a `name` equal to the field key. If the existing `Ui/Input.vue` does not forward a `name`, add `:name="field.key"` in the field components' `Input`/`Textarea`/`StyledSelect` usage and confirm those primitives pass `name` through via `$attrs` (Vue forwards unknown attributes to the single root element by default). If a primitive sets `inheritAttrs: false`, pass `name` explicitly.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Browser/Cp/FormFlowTest.php`
Expected: FAIL (`Cp/Resource/Form` page does not exist).

- [ ] **Step 3: Write the Form page**

```vue
<script setup>
import { computed } from 'vue';
import { useForm, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppHead from '../../../Components/AppHead.vue';
import FieldRenderer from '../../../Components/Fields/FieldRenderer.vue';
import Button from '../../../Components/Ui/Button.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    resource: { type: Object, required: true },
    record: { type: Object, default: null },
    values: { type: Object, required: true },
});

setLayoutProps({ mode: 'cp' });

const form = useForm({ ...props.values });

const isEdit = computed(() => props.record !== null);
const heading = computed(() => `${isEdit.value ? 'Edit' : 'New'} ${props.resource.label.toLowerCase()}`);

function submit() {
    if (isEdit.value) {
        form.put(`/cp/${props.resource.slug}/${props.record.id}`);
    } else {
        form.post(`/cp/${props.resource.slug}`);
    }
}
</script>

<template>
    <AppHead :og="{ title: heading }" />
    <h1 class="font-display text-display">{{ heading }}</h1>

    <form class="mt-8 flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <FieldRenderer
            v-for="field in resource.fields"
            :key="field.key"
            :field="field"
            :error="form.errors[field.key]"
            :model-value="form[field.key]"
            mode="edit"
            @update:model-value="form[field.key] = $event"
        />
        <div class="flex gap-3 pt-2">
            <Button type="submit" variant="primary" :disabled="form.processing">Save</Button>
            <Button :href="`/cp/${resource.slug}`" variant="secondary">Cancel</Button>
        </div>
    </form>
</template>
```

- [ ] **Step 4: Write the Index page**

```vue
<script setup>
import { ref, watch } from 'vue';
import { router, Link, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppHead from '../../../Components/AppHead.vue';
import Button from '../../../Components/Ui/Button.vue';
import Input from '../../../Components/Ui/Input.vue';
import Card from '../../../Components/Ui/Card.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    resource: { type: Object, required: true },
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
});

setLayoutProps({ mode: 'cp' });

const search = ref(props.filters.search ?? '');
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(`/cp/${props.resource.slug}`, { search: value }, { preserveState: true, replace: true });
    }, 250);
});

function cell(record, key) {
    const value = record[key];

    return value === null || value === undefined || value === '' ? 'Not set' : value;
}

function remove(id) {
    if (window.confirm('Delete this record?')) {
        router.delete(`/cp/${props.resource.slug}/${id}`);
    }
}
</script>

<template>
    <AppHead :og="{ title: resource.pluralLabel }" />

    <div class="flex items-center justify-between gap-4">
        <h1 class="font-display text-display">{{ resource.pluralLabel }}</h1>
        <Button :href="`/cp/${resource.slug}/create`" variant="primary">New</Button>
    </div>

    <div v-if="resource.searchable.length" class="mt-6 max-w-sm">
        <Input v-model="search" placeholder="Search" />
    </div>

    <Card variant="outline" class="mt-6 overflow-x-auto p-0">
        <table class="w-full text-meta">
            <thead>
                <tr class="border-b border-neutral-50 text-left text-label text-neutral-500">
                    <th v-for="column in resource.columns" :key="column.key" class="px-4 py-3 font-medium">{{ column.label }}</th>
                    <th class="px-4 py-3" />
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in records.data" :key="record.id" class="border-b border-neutral-25 last:border-0 hover:bg-neutral-25">
                    <td v-for="column in resource.columns" :key="column.key" class="px-4 py-3 text-neutral-900">
                        <Link :href="`/cp/${resource.slug}/${record.id}/edit`" class="block">{{ cell(record, column.key) }}</Link>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" class="text-label text-red-600 hover:text-red-700" @click="remove(record.id)">Delete</button>
                    </td>
                </tr>
                <tr v-if="records.data.length === 0">
                    <td :colspan="resource.columns.length + 1" class="px-4 py-8 text-center text-neutral-500">No records yet.</td>
                </tr>
            </tbody>
        </table>
    </Card>

    <div v-if="records.links" class="mt-6 flex flex-wrap gap-1">
        <Link
            v-for="link in records.links"
            :key="link.label"
            :href="link.url ?? ''"
            class="rounded-md px-3 py-1.5 text-label"
            :class="[link.active ? 'bg-accent-500 text-white' : 'text-neutral-700 hover:bg-neutral-25', !link.url && 'pointer-events-none opacity-40']"
            v-html="link.label"
        />
    </div>
</template>
```

- [ ] **Step 5: Write the DashboardController and replace the placeholder**

```php
<?php

namespace App\Http\Controllers\Cp;

use App\Cp\ResourceRegistry;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private ResourceRegistry $registry) {}

    public function index(): Response
    {
        $collections = [];

        foreach ($this->registry->all() as $resource) {
            $collections[$resource->group()][] = [
                'label' => $resource->pluralLabel(),
                'slug' => $resource->slug(),
                'count' => $resource->query()->count(),
            ];
        }

        $grouped = collect($collections)
            ->map(fn (array $items, string $group): array => ['group' => $group, 'items' => $items])
            ->values()
            ->all();

        return Inertia::render('Cp/Dashboard', ['collections' => $grouped]);
    }
}
```

In `routes/web.php`, change the dashboard route from the closure to:

```php
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
```

and add `use App\Http\Controllers\Cp\DashboardController;` to the imports.

- [ ] **Step 6: Replace the Dashboard page**

```vue
<script setup>
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppHead from '../../Components/AppHead.vue';
import Card from '../../Components/Ui/Card.vue';

defineOptions({ layout: AppLayout });

defineProps({
    collections: { type: Array, default: () => [] },
});

setLayoutProps({ mode: 'cp' });
</script>

<template>
    <AppHead :og="{ title: 'Control panel' }" />
    <h1 class="font-display text-display">Control panel</h1>

    <div v-for="group in collections" :key="group.group" class="mt-8">
        <h2 class="text-eyebrow uppercase text-neutral-500">{{ group.group }}</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Link v-for="item in group.items" :key="item.slug" :href="`/cp/${item.slug}`">
                <Card variant="outline" class="transition-colors hover:border-accent-500">
                    <p class="text-stat font-display text-neutral-900">{{ item.count }}</p>
                    <p class="text-meta text-neutral-700">{{ item.label }}</p>
                </Card>
            </Link>
        </div>
    </div>
</template>
```

- [ ] **Step 7: Run the browser test to verify it passes**

Run: `php artisan test --compact tests/Browser/Cp/FormFlowTest.php`
Expected: PASS (1 passed). If `fill()` cannot find inputs by name, apply the `name` forwarding fix described in Step 1 and re-run.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS (all green, including the pre-existing suite).

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/Pages/Cp app/Http/Controllers/Cp/DashboardController.php routes/web.php tests/Browser/Cp/FormFlowTest.php
git commit -m "feat: control panel dashboard, index, and form pages"
```

---

### Task 10: Edit affordance on entry pages

**Files:**
- Modify: `app/Http/Controllers/EntryController.php` (add an `editUrl` to the entry payload)
- Modify: `resources/js/Pages/Entry.vue` (show an Edit button when authenticated)
- Test: `tests/Feature/Cp/EntryEditLinkTest.php`

**Interfaces:**
- Consumes: `ResourceRegistry` (to map a model class to its resource slug), shared `auth.user`.
- Produces: the `Entry` Inertia page receives an `editUrl` prop (`/cp/{slug}/{id}/edit` or `null`). The entry header renders an Edit `Button` only when `auth.user` is present and `editUrl` is set.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Flight;
use App\Models\User;

it('includes an edit url for an authenticated user', function () {
    $flight = Flight::factory()->create();
    $entry = $flight->timelineEntry;

    $this->actingAs(User::factory()->create());

    $this->get($entry->url())->assertInertia(fn ($page) => $page
        ->where('editUrl', "/cp/flights/{$flight->id}/edit"));
})->skip(fn () => ! method_exists(\App\Models\Flight::class, 'timelineEntry'), 'Adjust to the project entry URL helper.');
```

Before implementing, confirm how `EntryController@show` resolves the model and how to build the entry URL in the test (inspect `app/Http/Controllers/EntryController.php` and the `HasTimelineEntry` concern). Replace `$entry->url()` and the `timelineEntry` access with the actual accessors used in the codebase, and remove the `skip` once the correct helper is wired. The assertion (`editUrl` equals `/cp/flights/{id}/edit`) stays the same.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EntryEditLinkTest`
Expected: FAIL (`editUrl` prop missing).

- [ ] **Step 3: Add a model-to-slug lookup on the registry**

Append to `app/Cp/ResourceRegistry.php`:

```php
public function findByModel(string $model): ?CpResource
{
    foreach ($this->all() as $resource) {
        if ($resource->model() === $model) {
            return $resource;
        }
    }

    return null;
}
```

- [ ] **Step 4: Add editUrl in EntryController**

In `app/Http/Controllers/EntryController.php`, where the `Inertia::render('Entry', [...])` payload is built (inspect the `entryPayload`/`show` method first), inject the registry and add the prop. Constructor:

```php
public function __construct(private \App\Cp\ResourceRegistry $registry) {}
```

When building the render array, add:

```php
'editUrl' => $this->editUrlFor($model),
```

and add the helper method (replace `$model` with the actual resolved model variable in `show`):

```php
/**
 * The control panel edit URL for a timeline model, or null when it has no resource.
 */
private function editUrlFor(\Illuminate\Database\Eloquent\Model $model): ?string
{
    $resource = $this->registry->findByModel($model::class);

    if ($resource === null) {
        return null;
    }

    return route('cp.resource.edit', ['resource' => $resource->slug(), 'id' => $model->getKey()]);
}
```

Note: `route('cp.resource.edit', ...)` produces an absolute URL; if the test expects a relative path, use `"/cp/{$resource->slug()}/{$model->getKey()}/edit"` directly instead. Match whichever the test asserts (the test asserts the relative path `/cp/flights/{id}/edit`, so build the string directly).

- [ ] **Step 5: Show the Edit button on the entry page**

In `resources/js/Pages/Entry.vue`, add to the props:

```js
editUrl: { type: String, default: null },
```

Add the `usePage` import and a computed for the logged-in user near the other script setup code:

```js
import { usePage } from '@inertiajs/vue3';
const pageProps = usePage();
const canEdit = computed(() => !!pageProps.props.auth?.user && !!props.editUrl);
```

In the header block, after the date `Link` (around line 89), add an Edit button:

```vue
<Button v-if="canEdit" :href="editUrl" variant="secondary" size="sm" class="mt-3">Edit</Button>
```

and import `Button` at the top:

```js
import Button from '../Components/Ui/Button.vue';
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=EntryEditLinkTest`
Expected: PASS (1 passed).

- [ ] **Step 7: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS (all green).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EntryController.php app/Cp/ResourceRegistry.php resources/js/Pages/Entry.vue tests/Feature/Cp/EntryEditLinkTest.php
git commit -m "feat: edit affordance on entry pages for the control panel"
```

---

## Self-Review

**Spec coverage:**
- Auth (single admin, command, middleware, shared user): Tasks 1, 2, 3. Covered.
- Context-aware shell (public unchanged): Task 4. Covered.
- Config-driven resources reusing TypeRegistry slugs: Tasks 5, 6. Covered.
- Generic controller + routes + validation: Task 7. Covered.
- Dual-mode field components + renderer: Task 8. Covered.
- Generic pages (dashboard, index, form): Task 9. Covered.
- Entry edit affordance (navigate, Phase 1): Task 10. Covered.
- Provenance locking, re-sync, editor/combobox, preview/edit toggle: explicitly Phase 2/3, out of scope here. Correct.

**Type consistency:** field definition shape (`key/label/type/options/rules/locked/help`) is consistent across `FieldGuesser`, `CpResource`, the controller's `prepare/recordValues/emptyValues`, and the Vue field components. The `resource.meta()` shape (`slug/label/pluralLabel/group/columns/fields/searchable`) matches what `Index.vue`/`Form.vue` consume. Nav shape (`{group, items:[{label, slug}]}`) matches `CpSidebarNav.vue` and the registry `nav()`.

**Known risks flagged inline (resolve during implementation, not plan failures):**
- `Schema::getColumnType` availability on this Laravel 13 install (native; no doctrine/dbal needed). If a column type returns unexpectedly, the name-based heuristics still apply.
- Browser-test `fill()` selecting inputs by `name` (Task 9 Step 1 documents the `name` forwarding fix).
- The exact entry-URL / model accessor in `EntryController` (Task 10 Steps 1, 4 instruct inspecting the real code first; the assertion is fixed).
- Route ordering for `/{resource}/create` vs `/{resource}` (Task 7 Step 6 documents placing `create` first).
