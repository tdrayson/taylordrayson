<?php

use App\Cp\CpResource;
use App\Cp\Resources\ArticleResource;
use App\Cp\Resources\NoteResource;
use App\Models\Article;

/** A minimal in-test resource with no declared sections. */
function plainResource(): CpResource
{
    return new class extends CpResource
    {
        public function model(): string
        {
            return Article::class;
        }

        public function slug(): string
        {
            return 'notes-test';
        }

        public function label(): string
        {
            return 'Note';
        }

        public function pluralLabel(): string
        {
            return 'Notes';
        }
    };
}

/** A resource that declares main + sidebar sections and a second tab. */
function sectionedResource(): CpResource
{
    return new class extends CpResource
    {
        public function model(): string
        {
            return Article::class;
        }

        public function slug(): string
        {
            return 'notes-test';
        }

        public function label(): string
        {
            return 'Note';
        }

        public function pluralLabel(): string
        {
            return 'Notes';
        }

        public function sections(): array
        {
            return [
                ['area' => 'main', 'fields' => ['title']],
                ['area' => 'sidebar', 'title' => 'Publish', 'fields' => ['occurred_at']],
                ['area' => 'main', 'tab' => 'SEO', 'title' => 'Meta', 'fields' => []],
            ];
        }
    };
}

it('places every field in one main section when none are declared', function () {
    $layout = plainResource()->layout();

    expect($layout['tabs'])->toBe(['Main']);
    expect($layout['sections'])->toHaveCount(1);
    expect($layout['sections'][0]['area'])->toBe('main');
    expect($layout['sections'][0]['tab'])->toBe('Main');

    $keys = collect($layout['sections'][0]['fields'])->pluck('key');
    expect($keys)->toContain('title');
});

it('resolves declared sections and derives tabs', function () {
    $layout = sectionedResource()->layout();

    expect($layout['tabs'])->toBe(['Main', 'SEO']);

    $main = collect($layout['sections'])->firstWhere('title', null);
    expect(collect($main['fields'])->pluck('key'))->toContain('title');

    $sidebar = collect($layout['sections'])->firstWhere('area', 'sidebar');
    expect($sidebar['title'])->toBe('Publish');
    expect(collect($sidebar['fields'])->pluck('key'))->toContain('occurred_at');
});

it('appends fields omitted from declared sections to a trailing main section', function () {
    $layout = sectionedResource()->layout();

    // `body`/other Note fields are not named in any section, so a trailing
    // main section must capture them rather than dropping them.
    $allKeys = collect($layout['sections'])->flatMap(fn ($s) => collect($s['fields'])->pluck('key'));
    $declaredKeys = collect(['title', 'occurred_at']);
    $leftover = collect((new Article)->getFillable())->diff($declaredKeys);

    foreach ($leftover as $key) {
        expect($allKeys)->toContain($key);
    }
});

it('includes the resolved layout in meta', function () {
    $meta = sectionedResource()->meta();

    expect($meta)->toHaveKey('layout');
    expect($meta['layout']['tabs'])->toBe(['Main', 'SEO']);
});

it('gives the article a main editor section and a publish sidebar', function () {
    $layout = (new ArticleResource)->layout();

    $sidebar = collect($layout['sections'])->firstWhere('area', 'sidebar');
    expect($sidebar)->not->toBeNull();

    $sidebarKeys = collect($sidebar['fields'])->pluck('key');
    expect($sidebarKeys)->toContain('draft');
    expect($sidebarKeys)->toContain('occurred_at');

    $mainKeys = collect($layout['sections'])
        ->where('area', 'main')
        ->flatMap(fn ($s) => collect($s['fields'])->pluck('key'));
    expect($mainKeys)->toContain('content');
});

it('gives the note a main + sidebar split without a draft field', function () {
    $layout = (new NoteResource)->layout();

    expect(collect($layout['sections'])->firstWhere('area', 'sidebar'))->not->toBeNull();
});
