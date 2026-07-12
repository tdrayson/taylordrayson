<?php

use App\Content\EntryFileRepository;
use App\Models\Article;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-authored-'.uniqid('', true));
    File::ensureDirectoryExists($this->contentPath);
    config(['content.path' => $this->contentPath]);
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }
});

it('writes an article as a json file with portable text preserved', function () {
    $blocks = [
        ['type' => 'block', 'children' => [['type' => 'span', 'text' => 'Hello']]],
    ];

    $article = Article::factory()->create([
        'occurred_at' => '2026-07-10 12:00:00',
        'title' => 'Flat File Article',
        'slug' => 'flat-file-article',
        'excerpt' => 'An excerpt',
        'content' => $blocks,
        'published' => true,
        'timezone' => 'Europe/London',
    ]);

    $path = $this->contentPath.'/2026/07/10/flat-file-article.json';

    expect(File::exists($path))->toBeTrue();

    $document = json_decode(File::get($path), true);

    expect($document['id'])->toBe($article->ulid)
        ->and($document['type'])->toBe('article')
        ->and($document['title'])->toBe('Flat File Article')
        ->and($document['published'])->toBeTrue()
        ->and($document['content'])->toBe($blocks);
});

it('writes a project markdown file with meta fields', function () {
    $project = Project::factory()->create([
        'occurred_at' => '2026-06-01 10:00:00',
        'title' => 'Portfolio Piece',
        'slug' => 'portfolio-piece',
        'description' => 'Short desc',
        'status' => 'shipped',
        'featured' => true,
    ]);

    $path = $this->contentPath.'/2026/06/01/portfolio-piece.md';

    expect(File::exists($path))->toBeTrue();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['id'])->toBe($project->ulid)
        ->and($parsed['frontmatter']['type'])->toBe('project')
        ->and($parsed['frontmatter']['title'])->toBe('Portfolio Piece')
        ->and($parsed['frontmatter']['status'])->toBe('shipped')
        ->and($parsed['frontmatter']['description'])->toBe('Short desc');
});

it('writes pages under content/pages by slug without a date folder', function () {
    $page = Page::factory()->create([
        'title' => 'Sleep Score',
        'slug' => 'sleep-score',
        'excerpt' => 'About sleep',
        'content' => [
            ['type' => 'block', 'children' => [['type' => 'span', 'text' => 'Page body']]],
        ],
        'published' => true,
    ]);

    $path = $this->contentPath.'/pages/sleep-score.json';

    expect(File::exists($path))->toBeTrue();

    $document = json_decode(File::get($path), true);

    expect($document['id'])->toBe($page->ulid)
        ->and($document['type'])->toBe('page')
        ->and($document['slug'])->toBe('sleep-score')
        ->and($document['content'][0]['children'][0]['text'])->toBe('Page body');
});
