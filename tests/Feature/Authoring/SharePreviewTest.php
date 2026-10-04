<?php

use App\Models\Article;
use App\Models\Book;
use App\Models\User;
use App\Support\OgRenderer;
use Illuminate\View\View;

beforeEach(function () {
    // Rendering the view still proves the card data is complete; only Browsershot is skipped.
    $this->mock(OgRenderer::class, fn ($mock) => $mock->shouldReceive('png')
        ->andReturnUsing(fn (View $view): string => $view->render() !== '' ? 'fake-png-bytes' : ''));
});

it('is only for a signed-in author', function () {
    $this->postJson('/entries/fuel/share-preview', [])->assertUnauthorized();
});

it('404s a type nobody authors', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/entries/sleep/share-preview', [])
        ->assertNotFound();
});

it('draws a new entry from unsaved values', function () {
    $response = $this->actingAs(User::factory()->create())
        ->postJson('/entries/fuel/share-preview', ['cost' => '61.20', 'price_per_litre' => '1.459', 'vehicle_id' => 'hn14wxp'])
        ->assertOk();

    expect($response->json('title'))->toBeString()->not->toBeEmpty()
        ->and($response->json('image'))->toBe('data:image/png;base64,'.base64_encode('fake-png-bytes'))
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('previews an edit to an existing entry without saving it', function () {
    $article = Article::factory()->create(['title' => 'Before']);

    $this->actingAs(User::factory()->create())
        ->postJson('/entries/article/share-preview', ['id' => $article->id, 'title' => 'After', 'unknown' => 'ignored'])
        ->assertOk()
        ->assertJsonPath('title', 'After');

    expect($article->fresh()->title)->toBe('Before');
});

it('merges an edited meta field over the stored meta', function () {
    $book = Book::factory()->create(['meta' => ['author' => 'Before', 'isbn' => '9780000000000']]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson('/entries/book/share-preview', ['id' => $book->id, 'meta.author' => 'Ursula K. Le Guin'])
        ->assertOk();

    expect($response->json('description'))->toContain('by Ursula K. Le Guin')
        ->and($book->fresh()->meta->author)->toBe('Before');
});

it('draws a page on the text card', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/entries/page/share-preview', ['title' => 'Uses', 'excerpt' => 'What I work with.'])
        ->assertOk()
        ->assertJsonPath('title', 'Uses')
        ->assertJsonPath('description', 'What I work with.');
});

it('refuses values the card cannot be drawn from', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/entries/fuel/share-preview', ['occurred_at' => 'not a date'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Could not draw the card from these values.');
});
