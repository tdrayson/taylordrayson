<?php

use App\Models\Citation;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('refuses a visitor who is not signed in', function () {
    $this->postJson('/citations/preview', ['url' => 'https://example.com/post', 'kind' => 'reply'])->assertUnauthorized();
});

it('returns a stored citation without fetching the page again', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'Stored title']);

    $this->actingAs(User::factory()->create())
        ->postJson('/citations/preview', ['url' => 'https://example.com/post', 'kind' => 'reply'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Stored title')
        ->assertJsonPath('data.label', 'Replied to');

    Http::assertNothingSent();
});

it('fetches and stores a post nothing has stored yet', function () {
    Http::fake(['https://example.com/new' => Http::response('<title>A fresh page</title>')]);

    $this->actingAs(User::factory()->create())
        ->postJson('/citations/preview', ['url' => 'https://example.com/new', 'kind' => 'reply'])
        ->assertOk()
        ->assertJsonPath('data.cited.title', 'A fresh page');

    expect(Citation::query()->where('url', 'https://example.com/new')->exists())->toBeTrue();
});

it('refetches a stored citation when asked to refresh', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'Old title']);
    Http::fake(['https://example.com/post' => Http::response('<title>New title</title>')]);

    $this->actingAs(User::factory()->create())
        ->postJson('/citations/preview', ['url' => 'https://example.com/post', 'kind' => 'reply', 'refresh' => true])
        ->assertJsonPath('data.cited.title', 'New title');
});
