<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LeaderboardEntry extends Model
{
    /** @var list<string> */
    protected $fillable = ['player_id', 'name', 'score'];

    /**
     * The leaderboard, keeping only each player's best score, highest first.
     * Each row carries the date that best was reached and a public fingerprint
     * (a one-way hash of the player id) used for highlighting and disambiguation.
     *
     * @return array<int, array{name: string, score: int, date: string|null, fp: string}>
     */
    public static function topEntries(?int $limit = 10): array
    {
        $best = [];

        self::query()
            ->orderByDesc('score')
            ->orderBy('created_at')
            ->get()
            ->each(function (self $entry) use (&$best): void {
                if (! isset($best[$entry->player_id])) {
                    $best[$entry->player_id] = [
                        'name' => $entry->name,
                        'score' => (int) $entry->score,
                        'date' => self::relativeDate($entry->created_at),
                        'fp' => self::fingerprint($entry->player_id),
                    ];
                }
            });

        $entries = array_values($best);

        return $limit === null ? $entries : array_slice($entries, 0, $limit);
    }

    /**
     * A public, non-reversible fingerprint of a player id, safe to expose.
     */
    public static function fingerprint(?string $playerId): string
    {
        return substr(hash('sha256', (string) $playerId), 0, 6);
    }

    /**
     * A short, human relative label for when a score landed: "2 hours ago",
     * "Yesterday", "3 days ago", or an absolute date once it's over a week old.
     */
    private static function relativeDate(?Carbon $time): ?string
    {
        if ($time === null) {
            return null;
        }

        if ($time->diffInSeconds(now()) < 60) {
            return 'Just now';
        }

        if ($time->isYesterday()) {
            return 'Yesterday';
        }

        if ($time->diffInDays(now()) < 7) {
            return $time->diffForHumans(['parts' => 1]);
        }

        return $time->isoFormat('D MMM YYYY');
    }

    /**
     * A player's best score, or null when they have never submitted.
     */
    public static function bestScoreFor(string $playerId): ?int
    {
        $best = self::query()->where('player_id', $playerId)->max('score');

        return $best === null ? null : (int) $best;
    }

    /**
     * A player's 1-based rank across the whole board (by their best score),
     * or null when they have never submitted.
     */
    public static function rankFor(string $playerId): ?int
    {
        $best = self::bestScoreFor($playerId);

        if ($best === null) {
            return null;
        }

        $ahead = self::query()
            ->selectRaw('player_id, MAX(score) as best')
            ->groupBy('player_id')
            ->havingRaw('MAX(score) > ?', [$best])
            ->get()
            ->count();

        return $ahead + 1;
    }
}
