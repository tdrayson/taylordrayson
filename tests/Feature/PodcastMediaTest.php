<?php

use App\Jobs\StorePodcastMedia;
use App\Models\Podcast;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

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
    Saloon::fake([
        '.mp3' => MockResponse::make('audio-bytes'),
        '' => MockResponse::make(podcastJpeg()),
    ]);
}

it('mirrors both artwork sizes into local storage', function () {
    fakePodcastFiles();
    $episode = mirroredEpisode();

    (new StorePodcastMedia($episode))->handle();

    $episode->refresh();

    expect($episode->getFirstMedia('cover'))->not->toBeNull()
        ->and($episode->getFirstMedia('artwork'))->not->toBeNull()
        // Named after the episode, not after the publisher's own filename.
        ->and($episode->getFirstMedia('cover')->file_name)->toStartWith('tww-s7-e255.');
});

/**
 * The audio is published from our own host, so a second copy of a ~10GB archive
 * would buy nothing. Pinned because mirroring it is the obvious thing to add back.
 */
it('does not mirror the audio', function () {
    fakePodcastFiles();
    $episode = mirroredEpisode();

    (new StorePodcastMedia($episode))->handle();

    expect($episode->refresh()->getMedia('audio'))->toBeEmpty()
        ->and($episode->audio_url)->toBe('https://media.example.test/episode-255.mp3');
});

/**
 * The point of the feature: stored artwork is served instead of the publisher's
 * URL, and the publisher's URL still works until there is one.
 */
it('serves the local artwork once mirrored and the publisher url before that', function () {
    $episode = mirroredEpisode();

    expect($episode->squareArtworkSrc())->toBe('https://example.test/square.jpg');

    fakePodcastFiles();
    (new StorePodcastMedia($episode))->handle();
    $episode->refresh();

    expect($episode->squareArtworkSrc())->not->toContain('example.test');
});

/** A re-run over the archive should cost nothing for episodes already stored. */
it('leaves an already mirrored episode alone unless forced', function () {
    fakePodcastFiles();
    $episode = mirroredEpisode();

    (new StorePodcastMedia($episode))->handle();
    $firstId = $episode->refresh()->getFirstMedia('artwork')->id;

    (new StorePodcastMedia($episode->refresh()))->handle();
    expect($episode->refresh()->getFirstMedia('artwork')->id)->toBe($firstId);

    (new StorePodcastMedia($episode->refresh(), force: true))->handle();
    expect($episode->refresh()->getFirstMedia('artwork')->id)->not->toBe($firstId);
});

it('retries rather than storing a partial file when the publisher fails', function () {
    Saloon::fake(['' => MockResponse::make('nope', 500)]);
    $episode = mirroredEpisode();

    expect(fn () => (new StorePodcastMedia($episode))->handle())
        ->toThrow(RuntimeException::class);

    expect($episode->refresh()->getFirstMedia('artwork'))->toBeNull();
});

it('queues only the episodes that are missing a copy', function () {
    fakePodcastFiles();
    $stored = mirroredEpisode();
    (new StorePodcastMedia($stored))->handle();

    Podcast::factory()->create(['season_number' => 7, 'episode_number' => 256]);

    Queue::fake();
    $this->artisan('podcast:media')->assertSuccessful();

    Queue::assertPushed(StorePodcastMedia::class, 1);
});
