<?php

use App\Models\Article;
use App\Models\Subject;
use App\Queries\MentionedSubjects;
use App\Queries\SubjectFeed;

it('offers a subject the prose names and has not tagged', function () {
    $subject = Subject::factory()->person()->create();
    $article = Article::factory()->create(['content' => [[
        '_type' => 'block', '_key' => 'a', 'children' => [
            ['_type' => 'mention', '_key' => 'm', 'kind' => 'subject', 'id' => $subject->id],
        ],
    ]]]);

    expect(app(MentionedSubjects::class)($article)->pluck('id')->all())->toBe([$subject->id]);
});

it('stops offering one once it is tagged', function () {
    $subject = Subject::factory()->person()->create();
    $article = Article::factory()->create(['content' => [[
        '_type' => 'block', '_key' => 'a', 'children' => [
            ['_type' => 'mention', '_key' => 'm', 'kind' => 'subject', 'id' => $subject->id],
        ],
    ]]]);
    $article->subjects()->attach($subject);

    expect(app(MentionedSubjects::class)($article))->toHaveCount(0);
});

it('does not put a mentioned entry on the subject\'s page by itself', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    Article::factory()->create(['content' => [[
        '_type' => 'block', '_key' => 'a', 'children' => [
            ['_type' => 'mention', '_key' => 'm', 'kind' => 'subject', 'id' => $subject->id],
        ],
    ]]]);

    expect(app(SubjectFeed::class)($subject))->toHaveCount(0);
});
