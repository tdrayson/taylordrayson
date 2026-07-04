<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

it('syncs tags by name, creating them on first use', function () {
    $article = Article::factory()->create();

    $article->syncTagNames(['Laravel', 'PHP']);

    expect($article->fresh()->tagNames())->toEqualCanonicalizing(['Laravel', 'PHP'])
        ->and(Tag::count())->toBe(2);
});

it('dedupes tag names that share the same slug', function () {
    $article = Article::factory()->create();

    $article->syncTagNames(['Web Development', 'web-development', ' Web Development ']);

    expect($article->fresh()->tagNames())->toHaveCount(1)
        ->and(Tag::count())->toBe(1);
});

it('reuses an existing tag across different models rather than creating duplicates', function () {
    $article = Article::factory()->create();
    $project = Project::factory()->create();

    $article->syncTagNames(['Laravel']);
    $project->syncTagNames(['Laravel']);

    expect(Tag::count())->toBe(1)
        ->and($project->fresh()->tagNames())->toEqualCanonicalizing(['Laravel']);
});

it('drops tags no longer present when syncing again', function () {
    $note = Note::factory()->create();

    $note->syncTagNames(['Coffee', 'Recipe']);
    $note->syncTagNames(['Coffee']);

    expect($note->fresh()->tagNames())->toEqualCanonicalizing(['Coffee']);
});

it('ignores blank tag names', function () {
    $project = Project::factory()->create();

    $project->syncTagNames(['Laravel', '  ', '']);

    expect($project->fresh()->tagNames())->toEqualCanonicalizing(['Laravel']);
});

it('removes taggable pivot rows when a tagged model is deleted', function () {
    $note = Note::factory()->create();
    $note->syncTagNames(['Coffee', 'Recipe']);

    expect(DB::table('taggables')->where('taggable_type', Note::class)->count())->toBe(2);

    $note->delete();

    expect(DB::table('taggables')->where('taggable_type', Note::class)->count())->toBe(0);
});
