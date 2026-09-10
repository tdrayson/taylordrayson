<?php

use App\Actions\Snippetclub\ClassifyPreformatted;
use App\Actions\Snippetclub\ConvertContent;
use App\Actions\Snippetclub\ImportPost;
use App\Actions\Snippetclub\RewriteLinks;
use App\Actions\Snippetclub\TagMap;
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

it('turns an advisory grey box into a callout, and says it did', function () {
    $result = (new ConvertContent)(
        '<!-- wp:preformatted --><pre>Make sure to replace line 3 with your API key.</pre><!-- /wp:preformatted -->'
    );

    expect($result->nodes[0])->toMatchArray(['_type' => 'callout', 'variant' => 'note'])
        ->and($result->nodes[0]['children'][0]['_type'])->toBe('block')
        ->and($result->nodes[0]['children'][0]['children'][0]['text'])->toBe('Make sure to replace line 3 with your API key.')
        ->and($result->notes)->toHaveCount(1);
});

it('leaves a short fragment as code, unremarked', function () {
    $result = (new ConvertContent)('<!-- wp:preformatted --><pre>[my_shortcode]</pre><!-- /wp:preformatted -->');

    expect($result->nodes[0]['_type'])->toBe('code')
        ->and($result->notes)->toBeEmpty();
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

// WordPress stores titles and term names HTML-encoded, so an ampersand arrives
// as an entity and would otherwise be title-cased into "Hooks &Amp; Filters".
it('decodes the entities WordPress stores in titles and tags', function () {
    app(ImportPost::class)([
        'title' => 'Removing Prefixes &amp; Suffixes',
        'slug' => 'prefixes',
        'status' => 'publish',
        'date' => '2022-11-16 19:27:41',
        'content_raw' => '',
        'tags' => [['name' => 'Design &amp; Build']],
    ]);

    $article = Article::where('slug', 'prefixes')->first();

    expect($article->title)->toBe('Removing Prefixes & Suffixes')
        // An unmapped tag, so this tests the decoding rather than the tag map.
        ->and($article->tagNames())->toContain('Design & Build');
});

// The old site had no callout, so a grey <pre> did both jobs: an aside to the
// reader, and a shortcode or path shown inline.
it('tells an aside apart from a code fragment in a grey box', function (string $text, ?string $variant) {
    expect(app(ClassifyPreformatted::class)($text))->toBe($variant);
})->with([
    ['Make sure your ACF date field returns in the format Ymd.', 'note'],
    ['Credit to Luke for the snippet.', 'note'],
    // Starts with a product name, so an allow-list of openings would miss it.
    ['OpenWeatherMap has a lenient free API tier that we can use.', 'note'],
    ['Note: Tooltip tutorial requires GenerateBlocks Pro.', 'important'],
    ["Note: You shouldn't rely soley on the AI generator for your Alt text.", 'warning'],
    ['[dynamic_fluentform field="fluent_form"]', null],
    ['/fluent-crm/app/Hooks/Handlers/ExternalPages.php', null],
    ['https://domain.com/?pw=password123', null],
    ['FluentForm\App\Modules\Component - line 548', null],
    ['Name = tct_author_meta Arguments = role', null],
    // An encoded polyline runs to 857 characters without a space, and PHP's
    // str_word_count still calls that 166 words.
    ['cwjwHggc@LCZ?DED@TGLQFELYDgAIsABo@GSGKAWEW@]Kw@@WCk@IYI{@Ia@Wo@GQSg@Gg@IOAaAPQ^@BAFGF@h@h@PFf@FVHH@FCXa@VmAPa@HYDET', null],
]);

// A snippet site tags by plugin, so two thirds of its tags named a product
// mentioned in exactly one post.
it('folds the old site\'s narrow tags into ones worth filtering by', function (array $source, array $expected) {
    expect(app(TagMap::class)->apply($source))->toEqualCanonicalizing($expected);
})->with([
    'a Pro tier is the same subject' => [['GeneratePress', 'GP Premium'], ['GeneratePress']],
    'a product acronym is spelled out' => [['ACF', 'Repeater'], ['Advanced Custom Fields']],
    'one-post plugins become WordPress' => [['SearchWP', 'Kadence', 'Options Page'], ['WordPress']],
    // 68 of 127 articles carried it, so it separated nothing.
    'hooks & filters was too broad to filter by' => [['Hooks & Filters'], ['WordPress']],
    'a library is the language it is written in' => [['LityJS', 'Flatpickr'], ['JavaScript']],
    'the broad ones are left alone' => [['PHP', 'CSS', 'Accessibility'], ['PHP', 'CSS', 'Accessibility']],
    'dead ones are dropped' => [['Demo', 'Cheatsheet'], []],
    // A tag added to the old site after this map was written should survive
    // rather than vanish silently.
    'an unmapped tag is kept' => [['Something New'], ['Something New']],
]);

// The importer overwrites content every run, so a fix made by hand would not
// survive one. Per-article corrections live in code for that reason.
it('applies a per-article correction, and survives a re-import', function () {
    $post = [
        'title' => 'Lightbox any Gutenberg image with Lity',
        'slug' => 'lightbox-any-gutenberg-image-with-lity',
        'status' => 'publish',
        'date' => '2022-09-01 10:00:00',
        'content_raw' => '<!-- wp:snippetclub/lightbox {"blockstudio":{"attributes":'
            .'{"target":"https://www.youtube.com/embed/dQw4w9WgXcQ","text":"Click Me"}}} /-->'
            .'<!-- wp:paragraph --><p>The real content.</p><!-- /wp:paragraph -->',
    ];

    $import = app(ImportPost::class);
    $import($post);
    $import($post);

    $content = Article::where('slug', 'lightbox-any-gutenberg-image-with-lity')->first()->content;

    expect(collect($content)->where('_type', 'video'))->toBeEmpty()
        ->and($content[0]['children'][0]['text'])->toBe('The real content.');
});
