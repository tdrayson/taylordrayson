<?php

use App\Models\LeaderboardEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

/**
 * Seed a play token in the cache, issued $ageSeconds ago, and return its nonce.
 */
function seedPlayToken(int $ageSeconds = 300): string
{
    $nonce = Str::uuid()->toString();
    Cache::put('snake:nonce:'.$nonce, now()->subSeconds($ageSeconds)->timestamp, 3600);

    return $nonce;
}

it('issues a play token', function () {
    postJson('/snake/token')
        ->assertSuccessful()
        ->assertJsonStructure(['nonce']);
});

it('records a valid score and returns the refreshed leaderboard', function () {
    $nonce = seedPlayToken();

    postJson('/snake/score', [
        'name' => 'Taylor',
        'score' => 12,
        'nonce' => $nonce,
        'player_id' => Str::uuid()->toString(),
    ])
        ->assertSuccessful()
        ->assertJsonPath('leaderboard.0.name', 'Taylor')
        ->assertJsonPath('leaderboard.0.score', 12)
        ->assertJsonPath('rank', 1)
        ->assertJsonPath('best', 12)
        ->assertJsonStructure(['fp']);

    expect(LeaderboardEntry::where('name', 'Taylor')->where('score', 12)->exists())->toBeTrue();
});

it('rejects a score with an unknown nonce', function () {
    postJson('/snake/score', [
        'name' => 'Taylor',
        'score' => 12,
        'nonce' => Str::uuid()->toString(),
        'player_id' => Str::uuid()->toString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nonce');
});

it('rejects a reused nonce', function () {
    $nonce = seedPlayToken();
    $player = Str::uuid()->toString();

    postJson('/snake/score', ['name' => 'Taylor', 'score' => 5, 'nonce' => $nonce, 'player_id' => $player])->assertSuccessful();

    postJson('/snake/score', ['name' => 'Taylor', 'score' => 5, 'nonce' => $nonce, 'player_id' => $player])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nonce');
});

it('rejects an implausibly fast score', function () {
    $nonce = seedPlayToken(ageSeconds: 0);

    postJson('/snake/score', [
        'name' => 'Cheater',
        'score' => 200,
        'nonce' => $nonce,
        'player_id' => Str::uuid()->toString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('score');
});

it('rejects a profane name', function () {
    $nonce = seedPlayToken();

    postJson('/snake/score', [
        'name' => 'f4ggot',
        'score' => 3,
        'nonce' => $nonce,
        'player_id' => Str::uuid()->toString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('keeps only each player best score on the board', function () {
    $sam = Str::uuid()->toString();
    $jo = Str::uuid()->toString();

    LeaderboardEntry::create(['player_id' => $sam, 'name' => 'Sam', 'score' => 10]);
    LeaderboardEntry::create(['player_id' => $sam, 'name' => 'Sam', 'score' => 40]);
    LeaderboardEntry::create(['player_id' => $jo, 'name' => 'Jo', 'score' => 25]);

    $board = LeaderboardEntry::topEntries();

    expect(collect($board)->map(fn (array $row): array => [$row['name'], $row['score']])->all())
        ->toBe([['Sam', 40], ['Jo', 25]]);
    expect($board[0])->toHaveKeys(['date', 'fp']);
});

it('keeps two players who share a name as separate rows', function () {
    LeaderboardEntry::create(['player_id' => Str::uuid()->toString(), 'name' => 'John', 'score' => 30]);
    LeaderboardEntry::create(['player_id' => Str::uuid()->toString(), 'name' => 'John', 'score' => 15]);

    $board = LeaderboardEntry::topEntries();

    expect($board)->toHaveCount(2);
    expect($board[0]['fp'])->not->toBe($board[1]['fp']);
});

it('renames a player across all their rows', function () {
    $player = Str::uuid()->toString();
    LeaderboardEntry::create(['player_id' => $player, 'name' => 'Tay', 'score' => 10]);
    LeaderboardEntry::create(['player_id' => $player, 'name' => 'Tay', 'score' => 20]);

    postJson('/snake/rename', ['name' => 'Taylor', 'player_id' => $player])
        ->assertSuccessful()
        ->assertJsonPath('leaderboard.0.name', 'Taylor');

    expect(LeaderboardEntry::where('player_id', $player)->where('name', 'Tay')->exists())->toBeFalse();
    expect(LeaderboardEntry::where('player_id', $player)->where('name', 'Taylor')->count())->toBe(2);
});

it('rejects a profane rename', function () {
    postJson('/snake/rename', ['name' => 'f4ggot', 'player_id' => Str::uuid()->toString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('renders the full leaderboard page', function () {
    LeaderboardEntry::create(['player_id' => Str::uuid()->toString(), 'name' => 'Sam', 'score' => 10]);

    $this->get('/leaderboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard')
            ->has('entries', 1)
        );
});
