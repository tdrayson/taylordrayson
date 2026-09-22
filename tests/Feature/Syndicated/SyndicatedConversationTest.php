<?php

use App\Enums\CommentStatus;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Note;
use App\Presenters\Conversation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('puts a kudo in the conversation as a like from Strava', function () {
    $note = Note::factory()->create();

    $note->syndicatedResponses()->create([
        'source' => Source::Strava->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Justin M.',
        'occurred_at' => now(),
    ]);

    $responses = Conversation::for($note)->toArray()['responses'];

    expect($responses)->toHaveCount(1)
        ->and($responses[0]['kind'])->toBe('like')
        ->and($responses[0]['authorName'])->toBe('Justin M.')
        ->and($responses[0]['source'])->toBe('strava')
        ->and($responses[0]['sourceName'])->toBe('Strava');
});

// The heading total is computed client-side from conversation.responses (no
// SSR in tests, per phpunit.xml), so the row it has to add up from is asserted
// on the Inertia prop rather than the rendered HTML.
it('counts a kudo toward the like total', function () {
    $note = Note::factory()->create();

    $note->syndicatedResponses()->create([
        'source' => Source::Strava->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Justin M.',
        'occurred_at' => now(),
    ]);

    Pest\Laravel\get($note->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('conversation.responses.0.kind', 'like')
            ->where('conversation.responses.0.source', 'strava'));
});

it('marks a platform response with the platform favicon, and a webmention with its site favicon', function () {
    app()->usePublicPath($public = sys_get_temp_dir().'/favicons-'.Str::random(8));
    File::ensureDirectoryExists("{$public}/favicons");
    File::put("{$public}/favicons/swarmapp.com.png", 'png-bytes');
    File::put("{$public}/favicons/jan.example.png", 'png-bytes');

    $note = Note::factory()->create();
    $note->syndicatedResponses()->create([
        'source' => Source::Swarm->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Clare A.',
        'occurred_at' => now(),
    ]);
    $note->webmentions()->create([
        'source_url' => 'https://jan.example/posts/now',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'mention',
        'author_name' => 'Jan',
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
    ]);

    $favicons = collect(Conversation::for($note)->toArray()['responses'])->pluck('sourceFavicon', 'kind');

    expect($favicons->all())->toEqual(['like' => '/favicons/swarmapp.com.png', 'mention' => '/favicons/jan.example.png']);

    File::deleteDirectory($public);
});
