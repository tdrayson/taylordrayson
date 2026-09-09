<?php

namespace App\Console\Commands\Fetch;

use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Models\Webmention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Keep the stored author photos current, and put back any that have gone.
 *
 * Two jobs in one pass, because both are the same fetch. A face is downloaded
 * once when a mention is verified and then never again, so it goes stale the
 * day somebody changes their avatar, and it is gone for good if the directory
 * is lost to a deploy. Neither is recoverable without asking again.
 *
 * Asking is done carefully. One request per distinct photo, however many
 * mentions share it; conditional, so an unchanged image is a 304 with no body;
 * and paced, so a run reads as a person catching up rather than a scraper.
 *
 * A photo whose URL has itself changed is out of reach here: only the page the
 * mention came from knows the new one, and re-reading that is re-verification,
 * not a refresh. Those keep the face they arrived with.
 */
#[Signature('webmentions:avatars {--missing : Only fetch the ones whose file has gone, leaving the rest alone}')]
#[Description('Re-download the author photos on webmentions, replacing any that have changed')]
class FetchWebmentionAvatars extends Command
{
    /** Four a second at most, so a long run never looks like a flood. */
    private const PAUSE_MICROSECONDS = 250_000;

    public function handle(StoreAuthorPhoto $storePhoto): int
    {
        // Grouped by the photo, not by the mention: somebody who has replied
        // ten times has one face, and asking their server ten times for it is
        // the behaviour that gets you blocked.
        $byPhoto = Webmention::query()
            ->whereNotNull('author_photo_url')
            ->get()
            ->groupBy('author_photo_url');

        if ($byPhoto->isEmpty()) {
            $this->components->warn('No webmentions carry an author photo.');

            return self::SUCCESS;
        }

        $stored = 0;
        $unchanged = 0;
        $skipped = 0;
        $unavailable = 0;

        foreach ($byPhoto as $photoUrl => $mentions) {
            $held = $mentions->every(fn (Webmention $mention): bool => $mention->author_photo_path !== null
                && File::exists(public_path($mention->author_photo_path)));

            if ($held && $this->option('missing')) {
                $skipped++;

                continue;
            }

            $path = $storePhoto($photoUrl, refresh: true);

            if ($path === null) {
                $this->components->warn(self::nameFor($mentions).' - photo unavailable');
                $unavailable++;

                usleep(self::PAUSE_MICROSECONDS);

                continue;
            }

            if ($storePhoto->outcome === 'unchanged') {
                $unchanged++;

                usleep(self::PAUSE_MICROSECONDS);

                continue;
            }

            Webmention::query()
                ->where('author_photo_url', $photoUrl)
                ->where(fn ($query) => $query->whereNot('author_photo_path', $path)->orWhereNull('author_photo_path'))
                ->update(['author_photo_path' => $path]);

            $this->components->task(self::nameFor($mentions));
            $stored++;

            usleep(self::PAUSE_MICROSECONDS);
        }

        $this->newLine();
        $this->components->info("Stored {$stored}, unchanged {$unchanged}, skipped {$skipped}, unavailable {$unavailable}.");

        return self::SUCCESS;
    }

    /** @param  Collection<int, Webmention>  $mentions */
    private static function nameFor($mentions): string
    {
        $mention = $mentions->first();
        $also = $mentions->count() - 1;

        return ($mention->author_name ?: $mention->source_url)
            .($also > 0 ? " (+{$also} more)" : '');
    }
}
