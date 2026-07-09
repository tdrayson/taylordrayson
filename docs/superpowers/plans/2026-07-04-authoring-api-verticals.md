# Authoring API Verticals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `/api/v1` REST verticals for Article, Page, Project, and Event (matching the existing Notes/Flights verticals), plus a reusable Portable Text validation rule and a tags-autocomplete endpoint, so the macOS authoring app can create/edit/delete hand-written content.

**Architecture:** Each type follows the established vertical: `Model → Actions (Create/Update/Delete) → FormRequest → Api/V1 Controller (apiResource) → V1 Resource`, all behind the `api.token` middleware inside the `v1` route group. Index endpoints reuse Plan 1's shared `ListRequest` for `from`/`to`/`per_page`. Rich-text bodies are stored as Portable Text JSON (cast to `array`) and validated at the API boundary by a shared `PortableText` rule enforcing a fixed node whitelist. Slug generation is centralised in a reusable action concern.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, Laravel Pint.

## Roadmap position & dependencies

This plan is the API-first roadmap's **Plan 3 (articles + pages rich text)** and **Plan 4 (remaining resource types: projects + events)**, merged into one execution.

- **Runs AFTER Plan 1** (`docs/superpowers/plans/2026-07-04-api-v1-foundation.md`) is complete. **Do not run concurrently** with the live Plan 1 session — both edit `routes/api.php`.
- **Consumes from Plan 1:** the shared `App\Http\Requests\Api\V1\ListRequest` (Task 7) and the Notes/Flights vertical conventions.
- **Rich-text format decision (Plan 3's call):** Portable Text is the recommendation carried from the design spec. It supersedes the current Editor.js block content for Articles (`app/Support/EditorJs.php`) and the earlier "TipTap JSON" lean. This is safe to adopt cleanly because Articles/Pages/Projects have **0 rows** — no legacy content to migrate. Confirm this decision before executing Task 1; once locked, `EditorJs::plainText` is no longer used by Articles and can be retired in a later cleanup (not in this plan).

## Global Constraints

- All models, factories, and DB tables **already exist** — do NOT recreate them. This plan wires the API layer only.
- All API routes live under `/api/v1`, named `api.v1.*`, behind `middleware('api.token')`.
- Responses use Laravel API Resources.
- **Index endpoints use Plan 1's shared `App\Http\Requests\Api\V1\ListRequest`** — accept `from`/`to` (date, `from` is `before_or_equal:to`) and `per_page` (1-100, default 25). Apply as `$request->applyTo($query)->orderByDesc('occurred_at')->paginate($request->perPage())`. **Do NOT hardcode `paginate(25)`.** `ListRequest::applyTo` filters on `occurred_at`.
- **Pages exception:** Pages have no `occurred_at`, so the Page index skips `applyTo` and orders by `title`, using only `$request->perPage()`.
- Date formatting in Resources: `occurred_at` / `started_at` → `->toDateTimeString()`; `created_at` / `updated_at` → `->toIso8601String()` (matches `NoteResource`).
- Rich-text bodies (`Article.content`, `Page.content`, `Project.long_description`) are stored as JSON (`array` cast) and validated with `new PortableText`.
- PHP style (project rules): explicit return types on every method; constructor property promotion; **always** use curly braces; PHPDoc array shapes over inline comments.
- After any PHP change, run `vendor/bin/pint --dirty --format agent` before committing.
- Tests are Pest feature tests in `tests/Feature/Api`. In `beforeEach`, set `config()->set('services.api.token', 'test-token')`; authenticate requests with `$this->withToken('test-token')`.
- Run tests with `php artisan test --compact --filter=<TestName>`.
- **Out of scope for this plan** (deliberate follow-ups): the image-upload endpoint (`POST /api/v1/attachments`) — it intersects the existing R2 + Spatie media-library design and needs its own brief; and the timezone model for `occurred_at` (currently a plain datetime, same as Notes).

## File structure

**New files:**
- `app/Rules/PortableText.php` — validation rule enforcing the node whitelist.
- `app/Actions/Concerns/GeneratesSlug.php` — reusable unique-slug trait.
- `app/Actions/Articles/{CreateArticle,UpdateArticle,DeleteArticle}.php`
- `app/Actions/Pages/{CreatePage,UpdatePage,DeletePage}.php`
- `app/Actions/Projects/{CreateProject,UpdateProject,DeleteProject}.php`
- `app/Actions/Events/{CreateEvent,UpdateEvent,DeleteEvent}.php`
- `app/Http/Requests/Api/V1/{Store,Update}{Article,Page,Project,Event}Request.php`
- `app/Http/Controllers/Api/V1/{Article,Page,Project,Event,Tag}Controller.php`
- `app/Http/Resources/V1/{Article,Page,Project,Event}Resource.php`
- `database/migrations/2026_07_04_120000_change_projects_long_description_to_json.php`
- `tests/Unit/PortableTextRuleTest.php`
- `tests/Feature/Api/{Article,Page,Project,Event,Tags}ApiTest.php`

**Modified files:**
- `app/Models/Project.php` — add `long_description` to casts.
- `app/Models/Event.php` — add numeric casts.
- `routes/api.php` — register the new resources and tags route.

---

### Task 1: Portable Text validation rule

**Files:**
- Create: `app/Rules/PortableText.php`
- Test: `tests/Unit/PortableTextRuleTest.php`

**Interfaces:**
- Produces: `App\Rules\PortableText` — an `implements ValidationRule` class usable as `new PortableText` on any `array` field. Fails when the value is not a list of allowed blocks. Allowed block `_type`: `block`, `image`, `code`, `divider`. Allowed block styles: `normal`, `h2`, `h3`, `h4`, `blockquote`. Allowed `listItem`: `bullet`, `number`. Allowed decorators: `strong`, `em`, `code`, `strike`. `link` annotations live in `markDefs` and require an `href`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Unit/PortableTextRuleTest.php

use App\Rules\PortableText;
use Illuminate\Support\Facades\Validator;

function validatePortableText(mixed $value): \Illuminate\Support\MessageBag
{
    return Validator::make(['content' => $value], ['content' => [new PortableText]])->errors();
}

it('passes a valid portable text document', function () {
    $doc = [
        ['_type' => 'block', '_key' => 'a', 'style' => 'h2', 'markDefs' => [],
         'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'Title', 'marks' => []]]],
        ['_type' => 'block', '_key' => 'b', 'style' => 'normal',
         'markDefs' => [['_type' => 'link', '_key' => 'l1', 'href' => 'https://x.com']],
         'children' => [['_type' => 'span', '_key' => 's2', 'text' => 'bold link', 'marks' => ['strong', 'l1']]]],
        ['_type' => 'image', '_key' => 'i1', 'url' => 'https://cdn/x.webp', 'alt' => 'x'],
        ['_type' => 'divider', '_key' => 'd1'],
    ];

    expect(validatePortableText($doc)->isEmpty())->toBeTrue();
});

it('rejects a non-array value', function () {
    expect(validatePortableText('just a string')->isNotEmpty())->toBeTrue();
});

it('rejects an unknown block type', function () {
    expect(validatePortableText([['_type' => 'iframe', '_key' => 'x']])->isNotEmpty())->toBeTrue();
});

it('rejects a disallowed heading style', function () {
    $doc = [['_type' => 'block', '_key' => 'a', 'style' => 'h1',
             'children' => [['_type' => 'span', '_key' => 's', 'text' => 'x', 'marks' => []]]]];
    expect(validatePortableText($doc)->isNotEmpty())->toBeTrue();
});

it('rejects a disallowed decorator', function () {
    $doc = [['_type' => 'block', '_key' => 'a', 'style' => 'normal',
             'children' => [['_type' => 'span', '_key' => 's', 'text' => 'x', 'marks' => ['highlight']]]]];
    expect(validatePortableText($doc)->isNotEmpty())->toBeTrue();
});

it('rejects an image without a url', function () {
    expect(validatePortableText([['_type' => 'image', '_key' => 'i']])->isNotEmpty())->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PortableTextRuleTest`
Expected: FAIL — `Class "App\Rules\PortableText" not found`.

- [ ] **Step 3: Write the rule**

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;

class PortableText implements ValidationRule
{
    private const BLOCK_TYPES = ['block', 'image', 'code', 'divider'];

    private const STYLES = ['normal', 'h2', 'h3', 'h4', 'blockquote'];

    private const LIST_ITEMS = ['bullet', 'number'];

    private const DECORATORS = ['strong', 'em', 'code', 'strike'];

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute field must be a Portable Text array.');

            return;
        }

        foreach ($value as $index => $block) {
            $this->validateBlock($block, "{$attribute}.{$index}", $fail);
        }
    }

    /**
     * @param  Closure(string): void  $fail
     */
    private function validateBlock(mixed $block, string $path, Closure $fail): void
    {
        if (! is_array($block) || ! isset($block['_type']) || ! is_string($block['_type'])) {
            $fail("The {$path} block is missing a string _type.");

            return;
        }

        if (! in_array($block['_type'], self::BLOCK_TYPES, true)) {
            $fail("The {$path} block type '{$block['_type']}' is not allowed.");

            return;
        }

        match ($block['_type']) {
            'block' => $this->validateTextBlock($block, $path, $fail),
            'image' => $this->requireString($block, 'url', $path, $fail),
            'code' => $this->requireString($block, 'code', $path, $fail),
            'divider' => null,
        };
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Closure(string): void  $fail
     */
    private function validateTextBlock(array $block, string $path, Closure $fail): void
    {
        $style = $block['style'] ?? 'normal';

        if (! in_array($style, self::STYLES, true)) {
            $fail("The {$path} style '{$style}' is not allowed.");
        }

        if (isset($block['listItem']) && ! in_array($block['listItem'], self::LIST_ITEMS, true)) {
            $fail("The {$path} listItem '{$block['listItem']}' is not allowed.");
        }

        $markDefKeys = Collection::make($block['markDefs'] ?? [])
            ->pluck('_key')->filter()->all();

        foreach ($block['children'] ?? [] as $childIndex => $span) {
            $childPath = "{$path}.children.{$childIndex}";

            if (! is_array($span) || ($span['_type'] ?? null) !== 'span') {
                $fail("The {$childPath} must be a span.");

                continue;
            }

            foreach ($span['marks'] ?? [] as $mark) {
                if (! in_array($mark, self::DECORATORS, true) && ! in_array($mark, $markDefKeys, true)) {
                    $fail("The {$childPath} mark '{$mark}' is not allowed.");
                }
            }
        }

        foreach ($block['markDefs'] ?? [] as $defIndex => $def) {
            if (($def['_type'] ?? null) === 'link' && empty($def['href'])) {
                $fail("The {$path}.markDefs.{$defIndex} link is missing an href.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Closure(string): void  $fail
     */
    private function requireString(array $block, string $key, string $path, Closure $fail): void
    {
        if (! isset($block[$key]) || ! is_string($block[$key]) || $block[$key] === '') {
            $fail("The {$path} block requires a non-empty {$key}.");
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PortableTextRuleTest`
Expected: PASS (6 passed).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Rules/PortableText.php tests/Unit/PortableTextRuleTest.php
git commit -m "feat: add Portable Text validation rule"
```

---

### Task 2: Reusable unique-slug concern

**Files:**
- Create: `app/Actions/Concerns/GeneratesSlug.php`
- Test: `tests/Unit/GeneratesSlugTest.php`

**Interfaces:**
- Produces: `App\Actions\Concerns\GeneratesSlug` trait with `protected function uniqueSlug(string $modelClass, string $source, ?int $ignoreId = null): string`. Returns a `Str::slug($source)` value made unique against `$modelClass`'s `slug` column by appending `-2`, `-3`, … Falls back to `untitled` when the source slugifies to empty. `$ignoreId` excludes a row (for updates).

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Unit/GeneratesSlugTest.php

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Article;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

$slugger = fn () => new class
{
    use GeneratesSlug;

    public function make(string $source, ?int $ignoreId = null): string
    {
        return $this->uniqueSlug(Article::class, $source, $ignoreId);
    }
};

it('slugifies a title', function () use ($slugger) {
    expect($slugger()->make('Hello World'))->toBe('hello-world');
});

it('disambiguates a taken slug', function () use ($slugger) {
    Article::factory()->create(['slug' => 'hello-world']);
    expect($slugger()->make('Hello World'))->toBe('hello-world-2');
});

it('ignores the current row on update', function () use ($slugger) {
    $article = Article::factory()->create(['slug' => 'hello-world']);
    expect($slugger()->make('Hello World', $article->id))->toBe('hello-world');
});

it('falls back to untitled for an empty slug', function () use ($slugger) {
    expect($slugger()->make('...'))->toBe('untitled');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=GeneratesSlugTest`
Expected: FAIL — trait not found.

- [ ] **Step 3: Write the trait**

```php
<?php

namespace App\Actions\Concerns;

use Illuminate\Support\Str;

trait GeneratesSlug
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function uniqueSlug(string $modelClass, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'untitled';
        $slug = $base;
        $suffix = 2;

        while (
            $modelClass::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=GeneratesSlugTest`
Expected: PASS (4 passed).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Concerns/GeneratesSlug.php tests/Unit/GeneratesSlugTest.php
git commit -m "feat: add reusable unique-slug action concern"
```

---

### Task 3: Migrate Project.long_description to JSON

**Files:**
- Create: `database/migrations/2026_07_04_120000_change_projects_long_description_to_json.php`
- Modify: `app/Models/Project.php` (casts)

**Interfaces:**
- Produces: `Project.long_description` stored as JSON and cast to `array` (Portable Text). Safe: 0 existing rows.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('long_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('long_description')->nullable()->change();
        });
    }
};
```

- [ ] **Step 2: Add the cast to the Project model**

In `app/Models/Project.php`, add `long_description` to the `casts()` array:

```php
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'long_description' => 'array',
            'tags' => 'array',
            'featured' => 'boolean',
            'started_at' => 'datetime',
        ];
    }
```

- [ ] **Step 3: Run the migration against the test database**

Run: `php artisan test --compact --filter=NoteApiTest`
Expected: PASS — confirms migrations (including the new one) run cleanly under `RefreshDatabase`.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_04_120000_change_projects_long_description_to_json.php app/Models/Project.php
git commit -m "feat: store project long_description as portable text json"
```

---

### Task 4: Article vertical

**Files:**
- Create: `app/Http/Requests/Api/V1/StoreArticleRequest.php`, `app/Http/Requests/Api/V1/UpdateArticleRequest.php`
- Create: `app/Actions/Articles/CreateArticle.php`, `app/Actions/Articles/UpdateArticle.php`, `app/Actions/Articles/DeleteArticle.php`
- Create: `app/Http/Resources/V1/ArticleResource.php`
- Create: `app/Http/Controllers/Api/V1/ArticleController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ArticleApiTest.php`

**Interfaces:**
- Consumes: `App\Rules\PortableText` (Task 1); `App\Actions\Concerns\GeneratesSlug` (Task 2); Plan 1's `App\Http\Requests\Api\V1\ListRequest`.
- Produces: `apiResource('articles')` under `/api/v1`. `CreateArticle::__invoke(array): Article`, `UpdateArticle::__invoke(Article, array): Article`, `DeleteArticle::__invoke(Article): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Api/ArticleApiTest.php

use App\Models\Article;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

function validArticlePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'My First Article',
        'excerpt' => 'A short summary.',
        'content' => [
            ['_type' => 'block', '_key' => 'a', 'style' => 'normal', 'markDefs' => [],
             'children' => [['_type' => 'span', '_key' => 's', 'text' => 'Hello.', 'marks' => []]]],
        ],
        'tags' => ['meta', 'writing'],
        'draft' => true,
        'occurred_at' => '2026-07-04 09:00:00',
    ], $overrides);
}

it('creates an article and derives a slug', function () {
    $this->withToken('test-token')->postJson('/api/v1/articles', validArticlePayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'My First Article')
        ->assertJsonPath('data.slug', 'my-first-article')
        ->assertJsonPath('data.draft', true)
        ->assertJsonPath('data.tags', ['meta', 'writing']);

    expect(Article::count())->toBe(1)
        ->and(Article::first()->timelineEntry)->not->toBeNull();
});

it('rejects invalid portable text content', function () {
    $this->withToken('test-token')->postJson('/api/v1/articles', validArticlePayload([
        'content' => [['_type' => 'iframe', '_key' => 'x']],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['content']);
});

it('requires a title', function () {
    $this->withToken('test-token')->postJson('/api/v1/articles', validArticlePayload(['title' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors(['title']);
});

it('disambiguates duplicate slugs', function () {
    Article::factory()->create(['slug' => 'my-first-article']);
    $this->withToken('test-token')->postJson('/api/v1/articles', validArticlePayload())
        ->assertCreated()->assertJsonPath('data.slug', 'my-first-article-2');
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/articles', validArticlePayload())->assertUnauthorized();
    expect(Article::count())->toBe(0);
});

it('lists articles newest first', function () {
    Article::factory()->create(['occurred_at' => '2026-07-01 10:00:00']);
    Article::factory()->create(['occurred_at' => '2026-07-03 10:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/articles')
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.occurred_at', fn ($v) => str_starts_with($v, '2026-07-03'));
});

it('applies from/to/per_page list filters', function () {
    Article::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Article::factory()->create(['occurred_at' => '2026-06-15 08:00:00']);
    Article::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/articles?from=2026-06-10&to=2026-06-30')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->withToken('test-token')->getJson('/api/v1/articles?per_page=2')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
});

it('shows, updates, and deletes an article', function () {
    $article = Article::factory()->create(['title' => 'Before']);

    $this->withToken('test-token')->getJson("/api/v1/articles/{$article->id}")
        ->assertOk()->assertJsonPath('data.title', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/articles/{$article->id}", ['title' => 'After'])
        ->assertOk()->assertJsonPath('data.title', 'After');

    $this->withToken('test-token')->deleteJson("/api/v1/articles/{$article->id}")
        ->assertNoContent();

    expect(Article::count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ArticleApiTest`
Expected: FAIL — route `/api/v1/articles` not found (404).

- [ ] **Step 3: Write the FormRequests**

```php
<?php

// app/Http/Requests/Api/V1/StoreArticleRequest.php

namespace App\Http\Requests\Api\V1;

use App\Rules\PortableText;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'array', new PortableText],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'draft' => ['sometimes', 'boolean'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

```php
<?php

// app/Http/Requests/Api/V1/UpdateArticleRequest.php

namespace App\Http\Requests\Api\V1;

use App\Rules\PortableText;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'required', 'array', new PortableText],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'draft' => ['sometimes', 'boolean'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Write the actions**

```php
<?php

// app/Actions/Articles/CreateArticle.php

namespace App\Actions\Articles;

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Article;

class CreateArticle
{
    use GeneratesSlug;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Article
    {
        return Article::create([
            'title' => $attributes['title'],
            'slug' => $this->uniqueSlug(Article::class, $attributes['slug'] ?? $attributes['title']),
            'excerpt' => $attributes['excerpt'] ?? null,
            'content' => $attributes['content'],
            'tags' => $attributes['tags'] ?? [],
            'draft' => $attributes['draft'] ?? false,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
```

```php
<?php

// app/Actions/Articles/UpdateArticle.php

namespace App\Actions\Articles;

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Article;

class UpdateArticle
{
    use GeneratesSlug;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Article $article, array $attributes): Article
    {
        if (array_key_exists('slug', $attributes)) {
            $attributes['slug'] = $this->uniqueSlug(Article::class, $attributes['slug'], $article->id);
        }

        $article->fill($attributes)->save();

        return $article->refresh();
    }
}
```

```php
<?php

// app/Actions/Articles/DeleteArticle.php

namespace App\Actions\Articles;

use App\Models\Article;

class DeleteArticle
{
    public function __invoke(Article $article): void
    {
        $article->delete();
    }
}
```

- [ ] **Step 5: Write the Resource**

```php
<?php

// app/Http/Resources/V1/ArticleResource.php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'tags' => $this->tags ?? [],
            'draft' => $this->draft,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Write the Controller**

```php
<?php

// app/Http/Controllers/Api/V1/ArticleController.php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Articles\CreateArticle;
use App\Actions\Articles\DeleteArticle;
use App\Actions\Articles\UpdateArticle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRequest;
use App\Http\Requests\Api\V1\StoreArticleRequest;
use App\Http\Requests\Api\V1\UpdateArticleRequest;
use App\Http\Resources\V1\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ArticleController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return ArticleResource::collection(
            $request->applyTo(Article::query())
                ->orderByDesc('occurred_at')
                ->paginate($request->perPage())
        );
    }

    public function store(StoreArticleRequest $request, CreateArticle $createArticle): JsonResponse
    {
        $article = $createArticle($request->validated());

        return ArticleResource::make($article)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Article $article): ArticleResource
    {
        return ArticleResource::make($article);
    }

    public function update(UpdateArticleRequest $request, Article $article, UpdateArticle $updateArticle): ArticleResource
    {
        return ArticleResource::make($updateArticle($article, $request->validated()));
    }

    public function destroy(Article $article, DeleteArticle $deleteArticle): Response
    {
        $deleteArticle($article);

        return response()->noContent();
    }
}
```

- [ ] **Step 7: Register the route**

In `routes/api.php`, add the import and the resource line inside the existing `v1` group:

```php
use App\Http\Controllers\Api\V1\ArticleController;
```

```php
Route::prefix('v1')->middleware('api.token')->name('api.v1.')->group(function () {
    Route::get('/ping', fn () => response()->json(['data' => ['ok' => true]]))->name('ping');
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('articles', ArticleController::class);
});
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ArticleApiTest`
Expected: PASS (8 passed).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/StoreArticleRequest.php app/Http/Requests/Api/V1/UpdateArticleRequest.php app/Actions/Articles app/Http/Resources/V1/ArticleResource.php app/Http/Controllers/Api/V1/ArticleController.php routes/api.php tests/Feature/Api/ArticleApiTest.php
git commit -m "feat: articles API vertical"
```

---

### Task 5: Page vertical

**Files:**
- Create: `app/Http/Requests/Api/V1/StorePageRequest.php`, `app/Http/Requests/Api/V1/UpdatePageRequest.php`
- Create: `app/Actions/Pages/CreatePage.php`, `app/Actions/Pages/UpdatePage.php`, `app/Actions/Pages/DeletePage.php`
- Create: `app/Http/Resources/V1/PageResource.php`
- Create: `app/Http/Controllers/Api/V1/PageController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/PageApiTest.php`

**Interfaces:**
- Consumes: `App\Rules\PortableText` (Task 1); `App\Actions\Concerns\GeneratesSlug` (Task 2); Plan 1's `App\Http\Requests\Api\V1\ListRequest`.
- Produces: `apiResource('pages')`. Pages have a **unique** slug, no `occurred_at`, no tags, and list ordered by `title` asc. `content` is nullable Portable Text.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Api/PageApiTest.php

use App\Models\Page;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

function validPagePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'About Me',
        'excerpt' => 'Who I am.',
        'content' => [
            ['_type' => 'block', '_key' => 'a', 'style' => 'normal', 'markDefs' => [],
             'children' => [['_type' => 'span', '_key' => 's', 'text' => 'Hi.', 'marks' => []]]],
        ],
        'draft' => false,
    ], $overrides);
}

it('creates a page with a unique slug', function () {
    $this->withToken('test-token')->postJson('/api/v1/pages', validPagePayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'About Me')
        ->assertJsonPath('data.slug', 'about-me');

    expect(Page::count())->toBe(1);
});

it('allows null content', function () {
    $this->withToken('test-token')->postJson('/api/v1/pages', validPagePayload(['content' => null]))
        ->assertCreated();
});

it('rejects invalid portable text content', function () {
    $this->withToken('test-token')->postJson('/api/v1/pages', validPagePayload([
        'content' => [['_type' => 'iframe', '_key' => 'x']],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['content']);
});

it('disambiguates duplicate slugs', function () {
    Page::factory()->create(['slug' => 'about-me']);
    $this->withToken('test-token')->postJson('/api/v1/pages', validPagePayload())
        ->assertCreated()->assertJsonPath('data.slug', 'about-me-2');
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/pages', validPagePayload())->assertUnauthorized();
    expect(Page::count())->toBe(0);
});

it('respects per_page', function () {
    Page::factory()->count(3)->create();

    $this->withToken('test-token')->getJson('/api/v1/pages?per_page=2')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
});

it('shows, updates, and deletes a page', function () {
    $page = Page::factory()->create(['title' => 'Before']);

    $this->withToken('test-token')->getJson("/api/v1/pages/{$page->id}")
        ->assertOk()->assertJsonPath('data.title', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/pages/{$page->id}", ['title' => 'After'])
        ->assertOk()->assertJsonPath('data.title', 'After');

    $this->withToken('test-token')->deleteJson("/api/v1/pages/{$page->id}")
        ->assertNoContent();

    expect(Page::count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PageApiTest`
Expected: FAIL — route not found (404).

- [ ] **Step 3: Write the FormRequests**

```php
<?php

// app/Http/Requests/Api/V1/StorePageRequest.php

namespace App\Http\Requests\Api\V1;

use App\Rules\PortableText;
use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'array', new PortableText],
            'draft' => ['sometimes', 'boolean'],
        ];
    }
}
```

```php
<?php

// app/Http/Requests/Api/V1/UpdatePageRequest.php

namespace App\Http\Requests\Api\V1;

use App\Rules\PortableText;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'array', new PortableText],
            'draft' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Write the actions**

```php
<?php

// app/Actions/Pages/CreatePage.php

namespace App\Actions\Pages;

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Page;

class CreatePage
{
    use GeneratesSlug;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Page
    {
        return Page::create([
            'title' => $attributes['title'],
            'slug' => $this->uniqueSlug(Page::class, $attributes['slug'] ?? $attributes['title']),
            'excerpt' => $attributes['excerpt'] ?? null,
            'content' => $attributes['content'] ?? null,
            'draft' => $attributes['draft'] ?? false,
        ]);
    }
}
```

```php
<?php

// app/Actions/Pages/UpdatePage.php

namespace App\Actions\Pages;

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Page;

class UpdatePage
{
    use GeneratesSlug;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Page $page, array $attributes): Page
    {
        if (array_key_exists('slug', $attributes)) {
            $attributes['slug'] = $this->uniqueSlug(Page::class, $attributes['slug'], $page->id);
        }

        $page->fill($attributes)->save();

        return $page->refresh();
    }
}
```

```php
<?php

// app/Actions/Pages/DeletePage.php

namespace App\Actions\Pages;

use App\Models\Page;

class DeletePage
{
    public function __invoke(Page $page): void
    {
        $page->delete();
    }
}
```

- [ ] **Step 5: Write the Resource**

```php
<?php

// app/Http/Resources/V1/PageResource.php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'draft' => $this->draft,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Write the Controller**

```php
<?php

// app/Http/Controllers/Api/V1/PageController.php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pages\CreatePage;
use App\Actions\Pages\DeletePage;
use App\Actions\Pages\UpdatePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRequest;
use App\Http\Requests\Api\V1\StorePageRequest;
use App\Http\Requests\Api\V1\UpdatePageRequest;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return PageResource::collection(
            Page::query()->orderBy('title')->paginate($request->perPage())
        );
    }

    public function store(StorePageRequest $request, CreatePage $createPage): JsonResponse
    {
        $page = $createPage($request->validated());

        return PageResource::make($page)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Page $page): PageResource
    {
        return PageResource::make($page);
    }

    public function update(UpdatePageRequest $request, Page $page, UpdatePage $updatePage): PageResource
    {
        return PageResource::make($updatePage($page, $request->validated()));
    }

    public function destroy(Page $page, DeletePage $deletePage): Response
    {
        $deletePage($page);

        return response()->noContent();
    }
}
```

- [ ] **Step 7: Register the route**

In `routes/api.php`, add the import and the resource line inside the `v1` group:

```php
use App\Http\Controllers\Api\V1\PageController;
```

```php
    Route::apiResource('pages', PageController::class);
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PageApiTest`
Expected: PASS (7 passed).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/StorePageRequest.php app/Http/Requests/Api/V1/UpdatePageRequest.php app/Actions/Pages app/Http/Resources/V1/PageResource.php app/Http/Controllers/Api/V1/PageController.php routes/api.php tests/Feature/Api/PageApiTest.php
git commit -m "feat: pages API vertical"
```

---

### Task 6: Project vertical

**Files:**
- Create: `app/Http/Requests/Api/V1/StoreProjectRequest.php`, `app/Http/Requests/Api/V1/UpdateProjectRequest.php`
- Create: `app/Actions/Projects/CreateProject.php`, `app/Actions/Projects/UpdateProject.php`, `app/Actions/Projects/DeleteProject.php`
- Create: `app/Http/Resources/V1/ProjectResource.php`
- Create: `app/Http/Controllers/Api/V1/ProjectController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ProjectApiTest.php`

**Interfaces:**
- Consumes: `App\Rules\PortableText` (Task 1); `App\Actions\Concerns\GeneratesSlug` (Task 2); Task 3's `long_description` array cast; Plan 1's `App\Http\Requests\Api\V1\ListRequest`.
- Produces: `apiResource('projects')`. `long_description` is nullable Portable Text; `description` stays a plain string. `status` is validated as a non-empty string (see note).

> **Note on `status`:** the spec's open items flag "Project.status allowed values" as TBC. Until confirmed, validate `status` as `['required', 'string', 'max:50']`. When the allowed set is agreed, tighten to `Rule::in([...])` in both requests.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Api/ProjectApiTest.php

use App\Models\Project;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

function validProjectPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Lifelog',
        'description' => 'A personal data project.',
        'long_description' => [
            ['_type' => 'block', '_key' => 'a', 'style' => 'normal', 'markDefs' => [],
             'children' => [['_type' => 'span', '_key' => 's', 'text' => 'The long story.', 'marks' => []]]],
        ],
        'url' => 'https://example.com',
        'github_url' => 'https://github.com/x/y',
        'status' => 'active',
        'featured' => true,
        'tags' => ['laravel', 'vue'],
        'started_at' => '2026-01-01 00:00:00',
        'occurred_at' => '2026-07-04 09:00:00',
    ], $overrides);
}

it('creates a project with portable text long_description', function () {
    $this->withToken('test-token')->postJson('/api/v1/projects', validProjectPayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Lifelog')
        ->assertJsonPath('data.slug', 'lifelog')
        ->assertJsonPath('data.featured', true)
        ->assertJsonPath('data.long_description.0._type', 'block');

    expect(Project::count())->toBe(1)
        ->and(Project::first()->timelineEntry)->not->toBeNull();
});

it('allows a null long_description', function () {
    $this->withToken('test-token')->postJson('/api/v1/projects', validProjectPayload(['long_description' => null]))
        ->assertCreated();
});

it('rejects invalid long_description portable text', function () {
    $this->withToken('test-token')->postJson('/api/v1/projects', validProjectPayload([
        'long_description' => [['_type' => 'iframe', '_key' => 'x']],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['long_description']);
});

it('rejects an invalid url', function () {
    $this->withToken('test-token')->postJson('/api/v1/projects', validProjectPayload(['url' => 'not-a-url']))
        ->assertUnprocessable()->assertJsonValidationErrors(['url']);
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/projects', validProjectPayload())->assertUnauthorized();
    expect(Project::count())->toBe(0);
});

it('applies from/to/per_page list filters', function () {
    Project::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Project::factory()->create(['occurred_at' => '2026-06-15 08:00:00']);
    Project::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/projects?from=2026-06-10&to=2026-06-30')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->withToken('test-token')->getJson('/api/v1/projects?per_page=2')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
});

it('shows, updates, and deletes a project', function () {
    $project = Project::factory()->create(['title' => 'Before']);

    $this->withToken('test-token')->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()->assertJsonPath('data.title', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/projects/{$project->id}", ['title' => 'After'])
        ->assertOk()->assertJsonPath('data.title', 'After');

    $this->withToken('test-token')->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    expect(Project::count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ProjectApiTest`
Expected: FAIL — route not found (404).

- [ ] **Step 3: Write the FormRequests**

```php
<?php

// app/Http/Requests/Api/V1/StoreProjectRequest.php

namespace App\Http\Requests\Api\V1;

use App\Rules\PortableText;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'array', new PortableText],
            'url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'featured' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'started_at' => ['nullable', 'date'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

```php
<?php

// app/Http/Requests/Api/V1/UpdateProjectRequest.php

namespace App\Http\Requests\Api\V1;

use App\Rules\PortableText;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'array', new PortableText],
            'url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'featured' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'started_at' => ['nullable', 'date'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Write the actions**

```php
<?php

// app/Actions/Projects/CreateProject.php

namespace App\Actions\Projects;

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Project;

class CreateProject
{
    use GeneratesSlug;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Project
    {
        return Project::create([
            'title' => $attributes['title'],
            'slug' => $this->uniqueSlug(Project::class, $attributes['slug'] ?? $attributes['title']),
            'description' => $attributes['description'] ?? null,
            'long_description' => $attributes['long_description'] ?? null,
            'url' => $attributes['url'] ?? null,
            'github_url' => $attributes['github_url'] ?? null,
            'status' => $attributes['status'],
            'featured' => $attributes['featured'] ?? false,
            'tags' => $attributes['tags'] ?? [],
            'started_at' => $attributes['started_at'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
```

```php
<?php

// app/Actions/Projects/UpdateProject.php

namespace App\Actions\Projects;

use App\Actions\Concerns\GeneratesSlug;
use App\Models\Project;

class UpdateProject
{
    use GeneratesSlug;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Project $project, array $attributes): Project
    {
        if (array_key_exists('slug', $attributes)) {
            $attributes['slug'] = $this->uniqueSlug(Project::class, $attributes['slug'], $project->id);
        }

        $project->fill($attributes)->save();

        return $project->refresh();
    }
}
```

```php
<?php

// app/Actions/Projects/DeleteProject.php

namespace App\Actions\Projects;

use App\Models\Project;

class DeleteProject
{
    public function __invoke(Project $project): void
    {
        $project->delete();
    }
}
```

- [ ] **Step 5: Write the Resource**

```php
<?php

// app/Http/Resources/V1/ProjectResource.php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'url' => $this->url,
            'github_url' => $this->github_url,
            'status' => $this->status,
            'featured' => $this->featured,
            'tags' => $this->tags ?? [],
            'started_at' => $this->started_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Write the Controller**

```php
<?php

// app/Http/Controllers/Api/V1/ProjectController.php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\UpdateProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRequest;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            $request->applyTo(Project::query())
                ->orderByDesc('occurred_at')
                ->paginate($request->perPage())
        );
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): JsonResponse
    {
        $project = $createProject($request->validated());

        return ProjectResource::make($project)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project): ProjectResource
    {
        return ProjectResource::make($project);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject): ProjectResource
    {
        return ProjectResource::make($updateProject($project, $request->validated()));
    }

    public function destroy(Project $project, DeleteProject $deleteProject): Response
    {
        $deleteProject($project);

        return response()->noContent();
    }
}
```

- [ ] **Step 7: Register the route**

In `routes/api.php`, add the import and the resource line inside the `v1` group:

```php
use App\Http\Controllers\Api\V1\ProjectController;
```

```php
    Route::apiResource('projects', ProjectController::class);
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ProjectApiTest`
Expected: PASS (7 passed).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/StoreProjectRequest.php app/Http/Requests/Api/V1/UpdateProjectRequest.php app/Actions/Projects app/Http/Resources/V1/ProjectResource.php app/Http/Controllers/Api/V1/ProjectController.php routes/api.php tests/Feature/Api/ProjectApiTest.php
git commit -m "feat: projects API vertical"
```

---

### Task 7: Event vertical

**Files:**
- Create: `app/Http/Requests/Api/V1/StoreEventRequest.php`, `app/Http/Requests/Api/V1/UpdateEventRequest.php`
- Create: `app/Actions/Events/CreateEvent.php`, `app/Actions/Events/UpdateEvent.php`, `app/Actions/Events/DeleteEvent.php`
- Create: `app/Http/Resources/V1/EventResource.php`
- Create: `app/Http/Controllers/Api/V1/EventController.php`
- Modify: `app/Models/Event.php` (add numeric casts), `routes/api.php`
- Test: `tests/Feature/Api/EventApiTest.php`

**Interfaces:**
- Consumes: Plan 1's `App\Http\Requests\Api\V1\ListRequest`.
- Produces: `apiResource('events')`. No slug, no rich text. `latitude`/`longitude` cast to `float`, `ticket_price` to `decimal:2`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Api/EventApiTest.php

use App\Models\Event;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

function validEventPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'gig',
        'name' => 'Radiohead',
        'venue_name' => 'O2 Arena',
        'address' => 'Peninsula Square',
        'city' => 'London',
        'country' => 'UK',
        'latitude' => 51.5030,
        'longitude' => 0.0032,
        'ticket_price' => 65.00,
        'notes' => 'Great show.',
        'occurred_at' => '2026-07-04 20:00:00',
    ], $overrides);
}

it('creates an event', function () {
    $this->withToken('test-token')->postJson('/api/v1/events', validEventPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Radiohead')
        ->assertJsonPath('data.city', 'London');

    expect(Event::count())->toBe(1)
        ->and(Event::first()->timelineEntry)->not->toBeNull();
});

it('requires a name and type', function () {
    $this->withToken('test-token')->postJson('/api/v1/events', validEventPayload(['name' => '', 'type' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'type']);
});

it('rejects a non-numeric ticket price', function () {
    $this->withToken('test-token')->postJson('/api/v1/events', validEventPayload(['ticket_price' => 'free']))
        ->assertUnprocessable()->assertJsonValidationErrors(['ticket_price']);
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/events', validEventPayload())->assertUnauthorized();
    expect(Event::count())->toBe(0);
});

it('applies from/to/per_page list filters', function () {
    Event::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Event::factory()->create(['occurred_at' => '2026-06-15 08:00:00']);
    Event::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/events?from=2026-06-10&to=2026-06-30')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->withToken('test-token')->getJson('/api/v1/events?per_page=2')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
});

it('shows, updates, and deletes an event', function () {
    $event = Event::factory()->create(['name' => 'Before']);

    $this->withToken('test-token')->getJson("/api/v1/events/{$event->id}")
        ->assertOk()->assertJsonPath('data.name', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/events/{$event->id}", ['name' => 'After'])
        ->assertOk()->assertJsonPath('data.name', 'After');

    $this->withToken('test-token')->deleteJson("/api/v1/events/{$event->id}")
        ->assertNoContent();

    expect(Event::count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=EventApiTest`
Expected: FAIL — route not found (404).

- [ ] **Step 3: Add numeric casts to the Event model**

In `app/Models/Event.php`, expand the `casts()` array:

```php
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'ticket_price' => 'decimal:2',
        ];
    }
```

- [ ] **Step 4: Write the FormRequests**

```php
<?php

// app/Http/Requests/Api/V1/StoreEventRequest.php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

```php
<?php

// app/Http/Requests/Api/V1/UpdateEventRequest.php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
```

- [ ] **Step 5: Write the actions**

```php
<?php

// app/Actions/Events/CreateEvent.php

namespace App\Actions\Events;

use App\Models\Event;

class CreateEvent
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Event
    {
        return Event::create([
            ...$attributes,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
```

```php
<?php

// app/Actions/Events/UpdateEvent.php

namespace App\Actions\Events;

use App\Models\Event;

class UpdateEvent
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Event $event, array $attributes): Event
    {
        $event->fill($attributes)->save();

        return $event->refresh();
    }
}
```

```php
<?php

// app/Actions/Events/DeleteEvent.php

namespace App\Actions\Events;

use App\Models\Event;

class DeleteEvent
{
    public function __invoke(Event $event): void
    {
        $event->delete();
    }
}
```

- [ ] **Step 6: Write the Resource**

```php
<?php

// app/Http/Resources/V1/EventResource.php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'type' => $this->type,
            'name' => $this->name,
            'venue_name' => $this->venue_name,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'ticket_price' => $this->ticket_price,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 7: Write the Controller**

```php
<?php

// app/Http/Controllers/Api/V1/EventController.php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\UpdateEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRequest;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return EventResource::collection(
            $request->applyTo(Event::query())
                ->orderByDesc('occurred_at')
                ->paginate($request->perPage())
        );
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $event = $createEvent($request->validated());

        return EventResource::make($event)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Event $event): EventResource
    {
        return EventResource::make($event);
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent): EventResource
    {
        return EventResource::make($updateEvent($event, $request->validated()));
    }

    public function destroy(Event $event, DeleteEvent $deleteEvent): Response
    {
        $deleteEvent($event);

        return response()->noContent();
    }
}
```

- [ ] **Step 8: Register the route**

In `routes/api.php`, add the import and the resource line inside the `v1` group:

```php
use App\Http\Controllers\Api\V1\EventController;
```

```php
    Route::apiResource('events', EventController::class);
```

- [ ] **Step 9: Run the test to verify it passes**

Run: `php artisan test --compact --filter=EventApiTest`
Expected: PASS (6 passed).

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/StoreEventRequest.php app/Http/Requests/Api/V1/UpdateEventRequest.php app/Actions/Events app/Http/Resources/V1/EventResource.php app/Http/Controllers/Api/V1/EventController.php app/Models/Event.php routes/api.php tests/Feature/Api/EventApiTest.php
git commit -m "feat: events API vertical"
```

---

### Task 8: Tags autocomplete endpoint

**Files:**
- Create: `app/Http/Controllers/Api/V1/TagController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TagsApiTest.php`

**Interfaces:**
- Consumes: `Article` and `Project` `tags` arrays.
- Produces: `GET /api/v1/tags?type={article|project}` → `{ "data": ["tag", …] }` (distinct, sorted). Invalid/missing `type` → `422`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Api/TagsApiTest.php

use App\Models\Article;
use App\Models\Project;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('returns distinct sorted article tags', function () {
    Article::factory()->create(['tags' => ['vue', 'laravel']]);
    Article::factory()->create(['tags' => ['laravel', 'php']]);

    $this->withToken('test-token')->getJson('/api/v1/tags?type=article')
        ->assertOk()
        ->assertJsonPath('data', ['laravel', 'php', 'vue']);
});

it('returns project tags', function () {
    Project::factory()->create(['tags' => ['side-project']]);

    $this->withToken('test-token')->getJson('/api/v1/tags?type=project')
        ->assertOk()
        ->assertJsonPath('data', ['side-project']);
});

it('rejects an unknown type', function () {
    $this->withToken('test-token')->getJson('/api/v1/tags?type=banana')
        ->assertUnprocessable();
});

it('rejects unauthenticated reads', function () {
    $this->getJson('/api/v1/tags?type=article')->assertUnauthorized();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=TagsApiTest`
Expected: FAIL — route not found (404).

- [ ] **Step 3: Write the Controller**

```php
<?php

// app/Http/Controllers/Api/V1/TagController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TagController extends Controller
{
    private const MODELS = [
        'article' => Article::class,
        'project' => Project::class,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->query('type');

        if (! is_string($type) || ! array_key_exists($type, self::MODELS)) {
            throw ValidationException::withMessages([
                'type' => 'The type must be one of: '.implode(', ', array_keys(self::MODELS)).'.',
            ]);
        }

        $model = self::MODELS[$type];

        $tags = $model::query()
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return response()->json(['data' => $tags]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, add the import and the route inside the `v1` group:

```php
use App\Http\Controllers\Api\V1\TagController;
```

```php
    Route::get('tags', TagController::class)->name('tags.index');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=TagsApiTest`
Expected: PASS (4 passed).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/TagController.php routes/api.php tests/Feature/Api/TagsApiTest.php
git commit -m "feat: tags autocomplete endpoint"
```

---

### Task 9: Full suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the whole test suite**

Run: `php artisan test --compact`
Expected: PASS — all existing tests plus the new Portable Text, slug, Article, Page, Project, Event, and Tags tests.

- [ ] **Step 2: Confirm the route table**

Run: `php artisan route:list --path=api/v1`
Expected: `notes` and `flights` (from Plan 1) plus the new `articles`, `pages`, `projects`, `events` resource routes, plus `tags` and `ping`, all under the `api.token` middleware.

- [ ] **Step 3: Final format check**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no outstanding style issues.

---

## Self-Review notes

- **Roadmap reconciliation:** index endpoints consume Plan 1's shared `ListRequest` (`from`/`to`/`per_page`) instead of hardcoded `paginate(25)`, matching the Notes/Flights contract. Pages correctly opt out of `applyTo` (no `occurred_at`) and use `perPage()` only. The Portable Text format is flagged as Plan 3's formal decision (supersedes Editor.js block content; greenfield, 0 rows). This plan must run AFTER Plan 1, not concurrently.
- **Spec coverage:** server prerequisites 1 (verticals), 2 (Portable Text rule), 3 (`long_description` migration), 5 (tags endpoint), 6 (timestamp exposure via Resources) are covered. Prerequisite 4 (media upload) is intentionally deferred (see Global Constraints) and needs its own brief. Timezone handling for `occurred_at` is out of scope (matches Notes).
- **Type consistency:** every action signature (`__invoke(array): Model`, `__invoke(Model, array): Model`, `__invoke(Model): void`), the `uniqueSlug(string, string, ?int): string` helper, and the `PortableText` rule are referenced identically across all consuming tasks.
- **Placeholders:** none — every step includes complete code and exact commands.
