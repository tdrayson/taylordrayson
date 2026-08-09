<?php

use App\Jobs\StorePodcastMedia;
use App\Models\Podcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * A real JPEG, so the queued `card` conversion has something Imagick can
 * actually decode when artwork lands in a converted collection.
 */
function podcastJpeg(): string
{
    $image = imagecreatetruecolor(600, 600);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function mirroredEpisode(): Podcast
{
    return Podcast::factory()->create([
        'season_number' => 7,
        'episode_number' => 255,
        'audio_url' => 'https://media.example.test/episode-255.mp3',
        'cover_image' => 'https://example.test/wide.jpg',
        'thumbnail' => 'https://example.test/square.jpg',
    ]);
}

function fakePodcastFiles(): void
{
    Http::fake([
        '*.mp3' => Http::response('audio-bytes'),
        '*' => Http::response(podcastJpeg()),
    ]);
}

it('mirrors the audio and both artwork sizes into local storage', function () {
    fakePodcastFiles();
    $episode = mirroredEpisode();

    (new StorePodcastMedia($episode))->handle();

    $episode->refresh();

    expect($episode->getFirstMedia('audio'))->not->toBeNull()
        ->and($episode->getFirstMedia('cover'))->not->toBeNull()
        ->and($episode->getFirstMedia('artwork'))->not->toBeNull()
        // Named after the episode, not after the publisher's own filename.
        ->and($episode->getFirstMedia('audio')->file_name)->toBe('tww-s7-e255.mp3');
});

/**
 * The whole point of the feature: a stored copy is served instead of the
 * publisher's URL, and the publisher's URL still works until there is one.
 */
it('serves the local copy once mirrored and the publisher url before that', function () {
    $episode = mirroredEpisode();

    expect($episode->audioSrc())->toBe('https://media.example.test/episode-255.mp3')
        ->and($episode->squareArtworkSrc())->toBe('https://example.test/square.jpg');

    fakePodcastFiles();
    (new StorePodcastMedia($episode))->handle();
    $episode->refresh();

    expect($episode->audioSrc())->not->toBe('https://media.example.test/episode-255.mp3')
        ->and($episode->audioSrc())->toContain('tww-s7-e255.mp3')
        ->and($episode->squareArtworkSrc())->not->toContain('example.test');
});

/**
 * A 40MB download is worth not repeating: a re-run over the archive should
 * cost nothing for episodes already stored.
 */
it('leaves an already mirrored episode alone unless forced', function () {
    fakePodcastFiles();
    $episode = mirroredEpisode();

    (new StorePodcastMedia($episode))->handle();
    $firstId = $episode->refresh()->getFirstMedia('audio')->id;

    (new StorePodcastMedia($episode->refresh()))->handle();
    expect($episode->refresh()->getFirstMedia('audio')->id)->toBe($firstId);

    (new StorePodcastMedia($episode->refresh(), force: true))->handle();
    expect($episode->refresh()->getFirstMedia('audio')->id)->not->toBe($firstId);
});

it('retries rather than storing a partial file when the publisher fails', function () {
    Http::fake(['*' => Http::response('nope', 500)]);
    $episode = mirroredEpisode();

    expect(fn () => (new StorePodcastMedia($episode))->handle())
        ->toThrow(RuntimeException::class);

    expect($episode->refresh()->getFirstMedia('audio'))->toBeNull();
});

it('queues only the episodes that are missing a copy', function () {
    fakePodcastFiles();
    $stored = mirroredEpisode();
    (new StorePodcastMedia($stored))->handle();

    Podcast::factory()->create(['season_number' => 7, 'episode_number' => 256, 'audio_url' => 'https://media.example.test/256.mp3']);
    // No audio to fetch, so nothing to queue for it either.
    Podcast::factory()->create(['season_number' => 7, 'episode_number' => 257, 'audio_url' => null]);

    Queue::fake();
    $this->artisan('podcast:media')->assertSuccessful();

    Queue::assertPushed(StorePodcastMedia::class, 1);
});
