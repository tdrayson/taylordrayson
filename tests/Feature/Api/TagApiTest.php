<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\Tag;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('lists tags alphabetically with their usage counts', function () {
    Note::factory()->create()->syncTagNames(['Recipe', 'Coffee']);
    Note::factory()->create()->syncTagNames(['Coffee']);

    $this->withToken('test-token')->getJson('/api/v1/tags')
        ->assertOk()
        ->assertJsonPath('data.0', ['name' => 'Coffee', 'slug' => 'coffee', 'count' => 2])
        ->assertJsonPath('data.1', ['name' => 'Recipe', 'slug' => 'recipe', 'count' => 1]);
});

it('keeps tags a picker still needs: unused ones, and those only on drafts', function () {
    Tag::create(['name' => 'Orphan', 'slug' => 'orphan']);
    Article::factory()->create(['published' => false])->syncTagNames(['Draft Only']);

    $this->withToken('test-token')->getJson('/api/v1/tags')
        ->assertOk()
        ->assertJsonPath('data.0', ['name' => 'Draft Only', 'slug' => 'draft-only', 'count' => 1])
        ->assertJsonPath('data.1', ['name' => 'Orphan', 'slug' => 'orphan', 'count' => 0]);
});

it('rejects unauthenticated reads', function () {
    $this->getJson('/api/v1/tags')->assertUnauthorized();
});
