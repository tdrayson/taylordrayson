<?php

use App\Actions\Files\BuildFileReleases;
use App\Actions\Files\ResolveReleaseAsset;
use App\Jobs\RefreshGitHubRelease;
use App\Models\Article;
use App\Services\GitHub\Client;
use App\Support\ReleaseCache;
use Illuminate\Support\Facades\Bus;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

$repo = 'tdrayson/global-styles-migrator';
$asset = 'global-styles-migrator.zip';
$url = 'https://github.com/tdrayson/global-styles-migrator/releases/latest/download/global-styles-migrator.zip';

/**
 * The fields of GET /repos/{owner}/{repo}/releases/latest the client reads.
 *
 * @return array<string, mixed>
 */
function githubReleasePayload(string $version = 'v1.4.0'): array
{
    return [
        'tag_name' => $version,
        'published_at' => '2026-08-01T09:00:00Z',
        'assets' => [
            ['name' => 'global-styles-migrator.zip', 'size' => 204800, 'content_type' => 'application/zip'],
        ],
    ];
}

it('resolves a warmed release asset with its version and size', function () use ($repo, $asset, $url) {
    Saloon::fake([MockResponse::make(githubReleasePayload())]);

    $this->artisan('releases:warm', ['repo' => [$repo]])->assertSuccessful();

    $file = app(ResolveReleaseAsset::class)($repo, $asset);

    expect($file->version)->toBe('v1.4.0')
        ->and($file->size)->toBe(204800)
        ->and($file->mime)->toBe('application/zip')
        ->and($file->url)->toBe($url)
        ->and($file->toArray()['releasedAt'])->toBe('1 August 2026');
});

it('warms every repo a document points at', function () use ($repo, $asset) {
    Article::factory()->create(['content' => [[
        '_type' => 'file',
        '_key' => 'abc123',
        'source' => 'github',
        'repo' => $repo,
        'asset' => $asset,
    ]]]);

    Saloon::fake([MockResponse::make(githubReleasePayload())]);

    $this->artisan('releases:warm')->assertSuccessful();

    expect(ReleaseCache::get($repo)['version'])->toBe('v1.4.0');
});

it('returns null when the github request fails', function () use ($repo) {
    Saloon::fake([MockResponse::make(['message' => 'Not Found'], 404)]);

    expect(app(Client::class)->latestRelease($repo))->toBeNull();
});

it('serves a stale release rather than waiting on github', function () use ($repo, $asset) {
    Saloon::fake([MockResponse::make(githubReleasePayload())]);
    $this->artisan('releases:warm', ['repo' => [$repo]])->assertSuccessful();

    $this->travel(2)->hours();
    Bus::fake();

    $file = app(ResolveReleaseAsset::class)($repo, $asset);

    expect($file->version)->toBe('v1.4.0')
        ->and($file->size)->toBe(204800);

    Bus::assertDispatched(RefreshGitHubRelease::class);
});

it('renders a cold cache without a version and asks for a refresh', function () use ($repo, $asset, $url) {
    Bus::fake();

    $file = app(ResolveReleaseAsset::class)($repo, $asset);

    expect($file->version)->toBeNull()
        ->and($file->size)->toBeNull()
        ->and($file->name)->toBe($asset)
        ->and($file->url)->toBe($url);

    Bus::assertDispatched(RefreshGitHubRelease::class);
});

it('keeps the stale entry when a refresh fails', function () use ($repo) {
    Saloon::fake([MockResponse::make(githubReleasePayload())]);
    $this->artisan('releases:warm', ['repo' => [$repo]])->assertSuccessful();

    Saloon::fake([MockResponse::make(['message' => 'Not Found'], 404)]);
    (new RefreshGitHubRelease($repo))->handle(app(Client::class));

    expect(ReleaseCache::get($repo)['version'])->toBe('v1.4.0');
});

it('keys a document\'s releases by repo and asset', function () use ($repo, $asset) {
    Saloon::fake([MockResponse::make(githubReleasePayload())]);
    $this->artisan('releases:warm', ['repo' => [$repo]])->assertSuccessful();

    $releases = app(BuildFileReleases::class)([
        ['_type' => 'file', '_key' => 'abc123', 'source' => 'github', 'repo' => $repo, 'asset' => $asset],
        ['_type' => 'file', '_key' => 'def456', 'source' => 'upload', 'url' => '/storage/notes.pdf'],
    ]);

    expect($releases)->toHaveCount(1)
        ->and($releases["{$repo}#{$asset}"]['version'])->toBe('v1.4.0')
        ->and($releases["{$repo}#{$asset}"]['size'])->toBe(204800);
});
