<?php

namespace App\Console\Commands\Fetch;

use App\Actions\Webmentions\StoreAuthorPhoto;
use App\Models\Webmention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Keep the stored author photos current, and put back any that have gone.
 *
 * Two jobs in one pass, because both are the same fetch. A face is downloaded
 * once when a mention is verified and then never again, so it goes stale the
 * day somebody changes their avatar, and it is gone for good if the directory
 * is lost to a deploy. Neither is recoverable without asking again.
 *
 * A photo whose URL has itself changed is out of reach here: only the page the
 * mention came from knows the new one, and re-reading that is re-verification,
 * not a refresh. Those keep the face they arrived with.
 */
#[Signature('webmentions:avatars {--missing : Only fetch the ones whose file has gone, leaving the rest alone}')]
#[Description('Re-download the author photos on approved webmentions, replacing any that have changed')]
class FetchWebmentionAvatars extends Command
{
    public function handle(StoreAuthorPhoto $storePhoto): int
    {
        $mentions = Webmention::query()
            ->whereNotNull('author_photo_url')
            ->get();

        if ($mentions->isEmpty()) {
            $this->components->warn('No webmentions carry an author photo.');

            return self::SUCCESS;
        }

        $replaced = 0;
        $skipped = 0;
        $kept = 0;

        foreach ($mentions as $mention) {
            $held = $mention->author_photo_path !== null
                && File::exists(public_path($mention->author_photo_path));

            if ($held && $this->option('missing')) {
                $skipped++;

                continue;
            }

            $path = $storePhoto($mention->author_photo_url, refresh: true);

            // The action hands back what it kept when a fetch fails, so an
            // unchanged path after a missing file means nothing was recovered.
            if ($path === null) {
                $this->components->warn("{$mention->author_name} - photo unavailable");
                $kept++;

                continue;
            }

            if ($mention->author_photo_path !== $path) {
                $mention->update(['author_photo_path' => $path]);
            }

            $this->components->task($mention->author_name ?? $mention->source_url);
            $replaced++;
        }

        $this->newLine();
        $this->components->info("Fetched {$replaced}, skipped {$skipped}, unavailable {$kept}.");

        return self::SUCCESS;
    }
}
