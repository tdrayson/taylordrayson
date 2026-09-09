<?php

use App\Actions\Snippetclub\ConvertContent;
use App\Actions\Snippetclub\ImportPost;
use App\Actions\Snippetclub\RewriteLinks;
use App\Models\Article;
use App\Support\Gutenberg\BlockParser;

it('reads nested blocks out of the markup', function () {
    $blocks = (new BlockParser)->parse(<<<'HTML'
    <!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->
    <!-- wp:fluent-crm/conditional-content {"tag_ids":[3]} -->
    <div><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph --></div>
    <!-- /wp:fluent-crm/conditional-content -->
    HTML);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[1]->name)->toBe('fluent-crm/conditional-content')
        ->and($blocks[1]->attributes)->toBe(['tag_ids' => [3]])
        ->and($blocks[1]->children[0]->name)->toBe('paragraph');
});

// The membership plugin renders to nothing for a logged-out request, so half
// the archive only exists in the markup. Unwrapping it is the whole migration.
it('keeps the content the membership gate used to hide', function () {
    $nodes = (new ConvertContent)(<<<'HTML'
    <!-- wp:fluent-crm/conditional-content {"tag_ids":[3]} -->
    <div><!-- wp:paragraph --><p>Members only.</p><!-- /wp:paragraph --></div>
    <!-- /wp:fluent-crm/conditional-content -->
    HTML)->nodes;

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['children'][0]['text'])->toBe('Members only.');
});

it('takes a code block from its attributes rather than its markup', function () {
    $nodes = (new ConvertContent)(
        '<!-- wp:blockstudio-element/code {"blockstudio":{"attributes":{"language":{"value":"php"},"lineNumbers":true,"code":"echo 1;"}}} /-->'
    )->nodes;

    expect($nodes[0])->toMatchArray(['_type' => 'code', 'code' => 'echo 1;', 'language' => 'php', 'lineNumbers' => true]);
});

it('tells a download apart from a link, and reads the size off the label', function () {
    $nodes = (new ConvertContent)(
        '<!-- wp:generateblocks/button --><a class="gb-button" href="https://example.com/a.zip">Download Plugin (54kb)</a><!-- /wp:generateblocks/button -->'
    )->nodes;

    expect($nodes[0])->toMatchArray([
        '_type' => 'file',
        'name' => 'a.zip',
        'size' => 55296,
        'title' => 'Download Plugin',
    ]);
});

it('drops the buttons that only jumped down the old page', function () {
    $nodes = (new ConvertContent)(
        '<!-- wp:generateblocks/button --><a href="#code">Skip to code</a><!-- /wp:generateblocks/button -->'
    )->nodes;

    expect($nodes)->toBeEmpty();
});

it('flattens a nested list into blocks carrying their own level', function () {
    $nodes = (new ConvertContent)(<<<'HTML'
    <!-- wp:list --><ul>
    <!-- wp:list-item --><li>One
    <!-- wp:list {"ordered":true} --><ol><!-- wp:list-item --><li>Deeper</li><!-- /wp:list-item --></ol><!-- /wp:list -->
    </li><!-- /wp:list-item -->
    </ul><!-- /wp:list -->
    HTML)->nodes;

    expect($nodes)->toHaveCount(2)
        ->and($nodes[0])->toMatchArray(['listItem' => 'bullet', 'level' => 1])
        ->and($nodes[1])->toMatchArray(['listItem' => 'number', 'level' => 2]);
});

it('lifts an image out of the paragraph it was written inside', function () {
    $nodes = (new ConvertContent)(
        '<!-- wp:paragraph --><p>Before <img src="https://example.com/a.png" alt="A"> after</p><!-- /wp:paragraph -->'
    )->nodes;

    expect($nodes)->toHaveCount(2)
        ->and($nodes[0]['_type'])->toBe('block')
        ->and($nodes[1])->toMatchArray(['_type' => 'image', 'url' => 'https://example.com/a.png', 'alt' => 'A']);
});

it('flags a preformatted box for review rather than guessing at it', function () {
    $result = (new ConvertContent)('<!-- wp:preformatted --><pre>A note.</pre><!-- /wp:preformatted -->');

    expect($result->nodes[0]['_type'])->toBe('code')
        ->and($result->notes)->toHaveCount(1);
});

it('imports a post, and importing it again changes nothing', function () {
    $post = [
        'title' => 'A tutorial',
        'slug' => 'a-tutorial',
        'status' => 'publish',
        'date' => '2022-11-16 19:27:41',
        'content_raw' => '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->',
        'tags' => [['name' => 'PHP']],
        'categories' => [['slug' => 'premium', 'name' => 'Premium'], ['slug' => 'tutorials', 'name' => 'Tutorials']],
    ];

    $import = app(ImportPost::class);

    expect($import($post)->created)->toBeTrue()
        ->and($import($post)->created)->toBeFalse()
        ->and(Article::where('slug', 'a-tutorial')->count())->toBe(1);

    $article = Article::where('slug', 'a-tutorial')->first();

    // `premium` described a paywall that no longer exists.
    expect($article->tagNames())->toEqualCanonicalizing(['PHP', 'Tutorials'])
        ->and($article->published)->toBeTrue()
        ->and($article->timezone)->toBe('Europe/London');
});

// WordPress leaves post_name empty until a post is published, so five drafts
// arrive with no slug and would otherwise collide on one row.
it('gives a slugless draft a slug of its own', function () {
    $import = app(ImportPost::class);

    $import(['title' => 'First draft', 'slug' => '', 'status' => 'draft', 'content_raw' => '']);
    $import(['title' => 'Second draft', 'slug' => '', 'status' => 'draft', 'content_raw' => '']);

    expect(Article::whereIn('slug', ['first-draft', 'second-draft'])->count())->toBe(2);
});

it('points the old site\'s own links at where those pages live now', function () {
    $article = Article::factory()->create(['slug' => 'a-tutorial', 'published' => true, 'occurred_at' => '2022-11-16 12:00:00']);

    $nodes = [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [
            ['_key' => 'm1', '_type' => 'link', 'href' => 'https://snippetclub.com/a-tutorial/'],
            ['_key' => 'm2', '_type' => 'link', 'href' => 'https://snippetclub.com/contact/'],
            ['_key' => 'm3', '_type' => 'link', 'href' => 'mailto:support@snippetclub.com'],
            ['_key' => 'm4', '_type' => 'link', 'href' => 'https://example.com/elsewhere'],
        ],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'Links', 'marks' => []]],
    ]];

    $result = app(RewriteLinks::class)($nodes);

    expect(array_column($result['nodes'][0]['markDefs'], 'href'))->toBe([
        '/'.$article->timelineEntry->occurred_at->format('Y/m/d').'/a-tutorial',
        '/contact',
        '/contact',
        // Somebody else's site is not ours to move.
        'https://example.com/elsewhere',
    ])->and($result['rewritten'])->toBe(3);
});

it('leaves a dead link to the old site alone and says so', function () {
    $result = app(RewriteLinks::class)([[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [['_key' => 'm1', '_type' => 'link', 'href' => 'https://snippetclub.com/gone/']],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'Gone', 'marks' => []]],
    ]]);

    expect($result['rewritten'])->toBe(0)
        ->and($result['unresolved'])->toBe(['https://snippetclub.com/gone/']);
});
