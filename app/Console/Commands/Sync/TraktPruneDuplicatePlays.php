<?php

namespace App\Console\Commands\Sync;

use App\Exceptions\TraktException;
use App\Models\Media;
use App\Services\Trakt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trakt:prune-duplicate-plays {--force : Actually delete; without this the command only reports}')]
#[Description('Remove spurious duplicate plays from Trakt history and their local media rows')]
class TraktPruneDuplicatePlays extends Command
{
    /**
     * The plays to remove, decided per-group from Trakt's real `watched_at`
     * and play ids rather than from local `occurred_at`.
     *
     * Two reasons this is a hand-checked manifest and not a heuristic:
     *
     * 1. Local `occurred_at` is not Trakt's `watched_at`. TraktSync's
     *    `nudgeTiedGroup()` spreads bulk-mark ties a second apart, so the
     *    stored times are derived, not observed.
     * 2. No single rule is correct for every group. Young Sheldon is a
     *    double-scrobble a minute apart where the FIRST play is genuine;
     *    Georgie & Mandy is a partial-then-full watch where the LAST play is
     *    genuine. A rule that gets one right gets the other wrong.
     *
     * `local_only` marks a play Trakt has already lost, where just the local
     * row needs clearing. Play ids are Trakt history ids (`media.source_id`),
     * NOT episode ids.
     *
     * @var list<array{label: string, delete: int, keep: int, reason: string, local_only?: bool}>
     */
    private const MANIFEST = [
        // Ten plays sharing just two timestamps (08:05:00 x7, 08:06:00 x3) in
        // Oct 2023, against genuine spread-out plays across Nov/Dec 2022.
        ['label' => 'Breaking Bad S3E7', 'delete' => 9283695329, 'keep' => 8677547398, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S3E8', 'delete' => 9283695360, 'keep' => 8677626282, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S3E9', 'delete' => 9283695396, 'keep' => 8677709753, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S3E10', 'delete' => 9283695407, 'keep' => 8687224231, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S3E11', 'delete' => 9283695409, 'keep' => 8687301394, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S3E12', 'delete' => 9283695413, 'keep' => 8687415011, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S3E13', 'delete' => 9283695418, 'keep' => 8696901763, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S4E1', 'delete' => 9283695521, 'keep' => 8697810814, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S4E2', 'delete' => 9283695529, 'keep' => 8711960243, 'reason' => '2023 bulk mark-as-watched'],
        ['label' => 'Breaking Bad S4E3', 'delete' => 9283695539, 'keep' => 8712020139, 'reason' => '2023 bulk mark-as-watched'],

        // Both groups are ties, so the timestamp cannot separate them. The
        // 19:32 plays carry HIGHER ids than the 20:15 plays, and Trakt ids
        // increment on creation: the 19:32 batch was added later with a
        // backdated time. The later-looking 20:15 plays are the originals.
        ['label' => 'You S1E7', 'delete' => 8588872776, 'keep' => 8582868084, 'reason' => 'backdated retro-add (higher id, earlier time)'],
        ['label' => 'You S1E9', 'delete' => 8588872778, 'keep' => 8582868071, 'reason' => 'backdated retro-add (higher id, earlier time)'],
        ['label' => 'You S1E10', 'delete' => 8588872779, 'keep' => 8582868055, 'reason' => 'backdated retro-add (higher id, earlier time)'],

        // Near-consecutive ids one minute apart: one watch scrobbled twice.
        ['label' => 'Young Sheldon S2E2', 'delete' => 10877741482, 'keep' => 10877741322, 'reason' => 'double-scrobble, keep first'],

        // Short gaps before a later full play: started, stopped, watched properly.
        ['label' => 'Georgie & Mandy S1E5', 'delete' => 11130302051, 'keep' => 11130330529, 'reason' => 'partial play 15m before the full one'],
        ['label' => 'Georgie & Mandy S1E8', 'delete' => 11130501828, 'keep' => 11130771371, 'reason' => 'partial play 2h before the full one'],
        ['label' => 'Georgie & Mandy S2E12', 'delete' => 13660216196, 'keep' => 13661346479, 'reason' => 'partial play 18m before the full one'],

        // Already absent from Trakt; only the local row needs clearing. It
        // survived because TraktSync is insert-only and never reconciles.
        ['label' => 'Jet Lag S18E6', 'delete' => 13922143413, 'keep' => 13922122239, 'reason' => 'local orphan, already gone from Trakt', 'local_only' => true],
    ];

    /**
     * Execute the console command.
     *
     * Order matters: Trakt is the source of truth, so nothing is removed
     * locally until Trakt has confirmed the corresponding play is gone. A
     * local row whose Trakt delete failed is left in place to be retried,
     * which is recoverable; the reverse would silently resurrect the play on
     * the next full sync.
     *
     * @return int Command exit code
     */
    public function handle(Trakt $trakt): int
    {
        $orphans = array_values(array_filter(self::MANIFEST, fn (array $e): bool => ($e['local_only'] ?? false) === true));
        $remote = array_values(array_filter(self::MANIFEST, fn (array $e): bool => ($e['local_only'] ?? false) === false));

        $this->table(
            ['Episode', 'Delete play', 'Keep play', 'Why'],
            array_map(fn (array $e): array => [
                $e['label'],
                $e['delete'].(($e['local_only'] ?? false) ? ' (local only)' : ''),
                $e['keep'],
                $e['reason'],
            ], self::MANIFEST),
        );

        $this->newLine();
        $this->line(sprintf(
            '%d plays to remove from Trakt, %d local-only row(s) to clear.',
            count($remote),
            count($orphans),
        ));

        if (! $this->option('force')) {
            $this->newLine();
            $this->info('Dry run. Nothing was changed. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Removing plays from Trakt is permanent and cannot be undone.');

        if (! $this->confirm('Delete these plays from your Trakt history?', false)) {
            $this->info('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $accessToken = $this->authorise($trakt);
            $result = $trakt->removeHistory(array_column($remote, 'delete'), $accessToken);
        } catch (TraktException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $deleted = (int) data_get($result, 'deleted.episodes', 0);
        $notFound = data_get($result, 'not_found.ids', []);

        $this->newLine();
        $this->info("Trakt removed {$deleted} play(s).");

        // Trakt answers 200 even when it matched nothing, so a short delete
        // count is reported rather than assumed successful.
        if ($deleted !== count($remote)) {
            $this->warn(sprintf('Expected %d, Trakt reported %d.', count($remote), $deleted));
        }

        if ($notFound !== []) {
            $this->warn('Trakt did not recognise: '.implode(', ', (array) $notFound));
        }

        // Only rows Trakt confirmed gone are cleared locally. Anything in
        // not_found still exists upstream and would be re-imported by the
        // next full sync, so deleting it here would just cause a silent
        // reappearance rather than a fixable error.
        $confirmedGone = array_diff(array_column($remote, 'delete'), (array) $notFound);
        $clearable = array_merge($confirmedGone, array_column($orphans, 'delete'));

        $cleared = Media::query()
            ->where('source', 'trakt')
            ->whereIn('source_id', array_map('strval', $clearable))
            ->delete();

        $this->info("Cleared {$cleared} local row(s).");

        return self::SUCCESS;
    }

    /**
     * Run the OAuth device flow, printing the code for the operator to enter.
     */
    private function authorise(Trakt $trakt): string
    {
        $device = $trakt->deviceCode();

        $this->newLine();
        $this->line("Go to <options=bold>{$device['verification_url']}</> and enter this code:");
        $this->newLine();
        $this->line("    <options=bold;fg=yellow>{$device['user_code']}</>");
        $this->newLine();
        $this->line('Waiting for authorisation...');

        return $trakt->pollForDeviceToken(
            $device['device_code'],
            (int) ($device['interval'] ?? 5),
            (int) ($device['expires_in'] ?? 600),
            fn (int $waited): mixed => $waited % 30 === 0 ? $this->line("  still waiting ({$waited}s)...") : null,
        );
    }
}
