<?php

use App\Models\Podcast;
use Illuminate\Support\Facades\Http;

/**
 * Every run rewrites the CSV mirror, so each test points it at a scratch file.
 * Without this the suite truncates the real data/podcasts.csv to whatever the
 * test factories happened to create.
 */
beforeEach(function () {
    $this->csv = sys_get_temp_dir().'/podcasts-test-'.getmypid().'.csv';
});

afterEach(function () {
    @unlink($this->csv);
});

/**
 * Build an episode payload in the shape the This Week With API returns.
 *
 * @return array<string, mixed>
 */
function podcastEpisode(int $number, string $date = '2026-07-24T13:00:00+01:00'): array
{
    return [
        'date' => $date,
        'season' => ['number' => 7],
        'episode_number' => $number,
        'main_topics' => "Topic {$number} -",
        'content' => '<p>Notes</p>',
        'duration' => 900,
        'audio_url' => "https://example.test/{$number}.mp3",
    ];
}

/**
 * Fake the paginated endpoint, one page per array, newest first.
 *
 * @param  list<list<array<string, mixed>>>  $pages
 */
function fakePodcastPages(array $pages): void
{
    $sequence = Http::sequence();

    foreach ($pages as $episodes) {
        $sequence->push(['episodes' => $episodes, 'total_pages' => count($pages)]);
    }

    Http::fake(['*wp-json/podcast/v1/episodes*' => $sequence]);
}

it('stores newly published episodes', function () {
    fakePodcastPages([[podcastEpisode(254), podcastEpisode(253)]]);

    $this->artisan("podcast:sync --csv={$this->csv}")->assertSuccessful();

    expect(Podcast::count())->toBe(2)
        ->and(Podcast::where('episode_number', 254)->first()->topic)->toBe('Topic 254');
});

it('stops paging once it reaches episodes it already has', function () {
    // Five known episodes end the run, so the second page is never requested.
    foreach ([254, 253, 252, 251, 250] as $number) {
        Podcast::factory()->create(['season_number' => 7, 'episode_number' => $number]);
    }

    fakePodcastPages([
        [podcastEpisode(254), podcastEpisode(253), podcastEpisode(252), podcastEpisode(251), podcastEpisode(250)],
        [podcastEpisode(249)],
    ]);

    $this->artisan("podcast:sync --csv={$this->csv}")->assertSuccessful();

    Http::assertSentCount(1);
    expect(Podcast::count())->toBe(5);
});

it('keeps going past a hole left by a half-finished run', function () {
    // 254 and 253 stored, 252 missing: a stop-at-the-first-known rule would
    // strand it forever.
    Podcast::factory()->create(['season_number' => 7, 'episode_number' => 254]);
    Podcast::factory()->create(['season_number' => 7, 'episode_number' => 253]);

    fakePodcastPages([[podcastEpisode(254), podcastEpisode(253), podcastEpisode(252)]]);

    $this->artisan("podcast:sync --csv={$this->csv}")->assertSuccessful();

    expect(Podcast::where('episode_number', 252)->exists())->toBeTrue();
});

it('re-maps the newest episodes so notes added after publication are picked up', function () {
    Podcast::factory()->create(['season_number' => 7, 'episode_number' => 254, 'topic' => 'Placeholder']);

    fakePodcastPages([[podcastEpisode(254)]]);

    $this->artisan("podcast:sync --csv={$this->csv}")->assertSuccessful();

    expect(Podcast::where('episode_number', 254)->first()->topic)->toBe('Topic 254');
});

it('walks the whole feed with --full', function () {
    foreach ([254, 253, 252, 251, 250] as $number) {
        Podcast::factory()->create(['season_number' => 7, 'episode_number' => $number]);
    }

    fakePodcastPages([
        [podcastEpisode(254), podcastEpisode(253), podcastEpisode(252), podcastEpisode(251), podcastEpisode(250)],
        [podcastEpisode(249)],
    ]);

    $this->artisan("podcast:sync --full --csv={$this->csv}")->assertSuccessful();

    Http::assertSentCount(2);
    expect(Podcast::where('episode_number', 249)->exists())->toBeTrue();
});

it('fails when the api returns nothing', function () {
    fakePodcastPages([[]]);

    $this->artisan("podcast:sync --csv={$this->csv}")->assertFailed();
});
