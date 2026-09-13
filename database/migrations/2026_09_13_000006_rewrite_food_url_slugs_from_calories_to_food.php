<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_BASE = 'calories';

    private const NEW_BASE = 'food';

    /**
     * Rewrite every food-dataset url_slug from `calories`/`calories`-N to
     * `food`, re-running the same day-scoped collision check
     * `ensureUrlSlug()` uses so a day where `food` (or `food`-N) is already
     * taken by another dataset still gets a free suffix rather than a
     * duplicate.
     */
    public function up(): void
    {
        $pattern = '/^'.preg_quote(self::OLD_BASE, '/').'(-\d+)?$/';

        $rows = DB::table('timeline_entries')
            ->where('dataset', 'food')
            ->where(fn ($query) => $query->where('url_slug', self::OLD_BASE)->orWhere('url_slug', 'like', self::OLD_BASE.'-%'))
            ->get(['id', 'url_slug', 'occurred_at']);

        foreach ($rows as $row) {
            if (preg_match($pattern, (string) $row->url_slug) !== 1) {
                continue;
            }

            $date = substr((string) $row->occurred_at, 0, 10);
            $taken = $this->takenSlugs(self::NEW_BASE, $row->id, $date);

            $candidate = self::NEW_BASE;
            $suffix = 1;

            while (in_array($candidate, $taken, true)) {
                $suffix++;
                $candidate = self::NEW_BASE.'-'.$suffix;
            }

            DB::table('timeline_entries')->where('id', $row->id)->update(['url_slug' => $candidate]);
        }
    }

    /**
     * Reverse: every food-dataset slug was `calories`/`calories`-N before up()
     * touched it, so swapping the base back is exact (no fresh collision
     * search needed, since up() never changes any other dataset's row).
     */
    public function down(): void
    {
        $pattern = '/^'.preg_quote(self::NEW_BASE, '/').'(-\d+)?$/';

        $rows = DB::table('timeline_entries')
            ->where('dataset', 'food')
            ->where(fn ($query) => $query->where('url_slug', self::NEW_BASE)->orWhere('url_slug', 'like', self::NEW_BASE.'-%'))
            ->get(['id', 'url_slug']);

        foreach ($rows as $row) {
            if (preg_match($pattern, (string) $row->url_slug, $matches) !== 1) {
                continue;
            }

            $suffix = $matches[1] ?? '';

            DB::table('timeline_entries')->where('id', $row->id)->update(['url_slug' => self::OLD_BASE.$suffix]);
        }
    }

    /**
     * @return list<string>
     */
    private function takenSlugs(string $base, int $excludingId, string $date): array
    {
        $pattern = '/^'.preg_quote($base, '/').'(-\d+)?$/';

        return DB::table('timeline_entries')
            ->where('id', '!=', $excludingId)
            ->whereDate('occurred_at', $date)
            ->where(fn ($query) => $query->where('url_slug', $base)->orWhere('url_slug', 'like', "{$base}-%"))
            ->pluck('url_slug')
            ->filter(fn (?string $slug): bool => $slug !== null && preg_match($pattern, $slug) === 1)
            ->values()
            ->all();
    }
};
