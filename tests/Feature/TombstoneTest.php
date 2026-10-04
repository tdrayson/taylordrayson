<?php

use App\Jobs\SendWebmention;
use App\Models\Note;
use App\Models\Page;
use App\Models\Tombstone;
use App\Models\WebmentionSend;
use App\Support\PortableText;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

beforeEach(function () {
    Queue::fake([SendWebmention::class]);
});

it('answers 410 with a tombstone at a deleted post\'s address', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-10-04 09:00:00']);
    $url = $note->url();

    $note->delete();

    get($url)->assertStatus(410)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Deleted')
        ->where('url', url($url))
        ->has('deletedAt'));
});

it('leaves a tombstone for a deleted page too', function () {
    $page = Page::factory()->create(['slug' => 'uses']);

    $page->delete();

    get('/uses')->assertStatus(410);
});

it('leaves nothing for a post that was never public', function (string $status) {
    $note = Note::factory()->create(['occurred_at' => '2026-10-04 09:00:00', 'status' => $status, 'password' => 'secret']);

    $note->delete();

    expect(Tombstone::query()->count())->toBe(0);
})->with(['draft', 'private']);

it('serves a post published later at the same address instead of its tombstone', function () {
    $first = Note::factory()->create(['occurred_at' => '2026-10-04 09:00:00', 'slug' => 'same-place']);
    $url = $first->url();
    $first->delete();

    Note::factory()->create(['occurred_at' => '2026-10-04 10:00:00', 'slug' => 'same-place']);

    get($url)->assertOk();
});

it('tells every site that took a mention from the post that it is gone', function () {
    config(['webmentions.send' => true]);

    $note = Note::factory()->create(['occurred_at' => '2026-10-04 09:00:00', 'content' => PortableText::fromPlainText('No links.')]);
    $sourceUrl = rtrim(config('app.url'), '/').$note->url();

    foreach (['sent' => 'https://a.example/post', 'failed' => 'https://b.example/post', 'unsupported' => 'https://c.example/post'] as $status => $target) {
        WebmentionSend::query()->create(['source_url' => $sourceUrl, 'target_url' => $target, 'status' => $status, 'attempts' => 1, 'content_hash' => 'x']);
    }

    $note->delete();

    Queue::assertPushed(SendWebmention::class, 2);
    Queue::assertPushed(SendWebmention::class, fn (SendWebmention $job): bool => $job->sourceUrl === $sourceUrl
        && $job->target === 'https://a.example/post'
        && $job->sourceType === null);
    Queue::assertNotPushed(SendWebmention::class, fn (SendWebmention $job): bool => $job->target === 'https://c.example/post');
});
