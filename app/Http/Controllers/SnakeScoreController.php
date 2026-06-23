<?php

namespace App\Http\Controllers;

use App\Http\Requests\RenameSnakePlayerRequest;
use App\Http\Requests\StoreSnakeScoreRequest;
use App\Models\LeaderboardEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SnakeScoreController extends Controller
{
    /**
     * How long a play token is valid for, and the cache key it lives under.
     */
    private const TOKEN_TTL_SECONDS = 3600;

    private const TOKEN_PREFIX = 'snake:nonce:';

    /**
     * The fastest a point could plausibly be earned. Submissions claiming a
     * score the elapsed play time can't account for are rejected.
     */
    private const MIN_SECONDS_PER_POINT = 0.25;

    /**
     * Issue a single-use play token when a game starts. Stored against the
     * moment of issue so the score endpoint can sanity-check play duration.
     */
    public function token(): JsonResponse
    {
        $nonce = Str::uuid()->toString();

        Cache::put(self::TOKEN_PREFIX.$nonce, now()->timestamp, self::TOKEN_TTL_SECONDS);

        return response()->json(['nonce' => $nonce]);
    }

    /**
     * Record a finished game's score and return the refreshed leaderboard.
     */
    public function store(StoreSnakeScoreRequest $request): JsonResponse
    {
        $playerId = $request->validated('player_id');
        $name = $request->validated('name');
        $score = (int) $request->validated('score');

        $issuedAt = Cache::pull(self::TOKEN_PREFIX.$request->validated('nonce'));

        if ($issuedAt === null) {
            throw ValidationException::withMessages([
                'nonce' => 'Play a game before saving a score.',
            ]);
        }

        if (now()->timestamp - (int) $issuedAt < $score * self::MIN_SECONDS_PER_POINT) {
            throw ValidationException::withMessages([
                'score' => 'That score came in too fast to be real.',
            ]);
        }

        LeaderboardEntry::create(['player_id' => $playerId, 'name' => $name, 'score' => $score]);

        return response()->json([
            'leaderboard' => LeaderboardEntry::topEntries(5),
            'rank' => LeaderboardEntry::rankFor($playerId),
            'best' => LeaderboardEntry::bestScoreFor($playerId),
            'fp' => LeaderboardEntry::fingerprint($playerId),
        ]);
    }

    /**
     * Rename a player across all their existing leaderboard rows.
     */
    public function rename(RenameSnakePlayerRequest $request): JsonResponse
    {
        $playerId = $request->validated('player_id');

        LeaderboardEntry::query()
            ->where('player_id', $playerId)
            ->update(['name' => $request->validated('name')]);

        return response()->json([
            'leaderboard' => LeaderboardEntry::topEntries(5),
            'rank' => LeaderboardEntry::rankFor($playerId),
            'best' => LeaderboardEntry::bestScoreFor($playerId),
            'fp' => LeaderboardEntry::fingerprint($playerId),
        ]);
    }
}
