<?php

namespace App\Console\Commands\Fetch;

use App\Jobs\StorePodcastMedia;
use App\Models\Podcast;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

#[Signature('podcast:media
    {--force : Re-download episodes that already have a stored copy}
    {--limit= : Stop after this many episodes, for filling the archive in batches}
    {--now : Download in the foreground instead of queueing, with a progress bar}')]
#[Description('Mirror This Week With episode audio and artwork into local storage')]
class DownloadPodcastMedia extends Command
{
    /**
     * Back-fill the archive, one job per episode. Queued by default so the ~10GB
     * catalogue survives an interruption; `--now` runs a handful in the foreground.
     */
    public function handle(): int
    {
        $episodes = $this->targetEpisodes();

        if ($episodes->isEmpty()) {
            $this->components->info('Every episode already has a stored copy.');

            return self::SUCCESS;
        }

        $this->components->info($episodes->count().' episode(s) to mirror.');

        return $this->option('now')
            ? $this->downloadNow($episodes)
            : $this->queue($episodes);
    }

    /**
     * @param  Collection<int, Podcast>  $episodes
     */
    private function queue(Collection $episodes): int
    {
        $episodes->each(fn (Podcast $episode) => StorePodcastMedia::dispatch($episode, (bool) $this->option('force')));

        $this->components->info('Queued '.$episodes->count().' episode(s). Run a queue worker to process them.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Podcast>  $episodes
     */
    private function downloadNow(Collection $episodes): int
    {
        $failed = 0;
        $bar = $this->output->createProgressBar($episodes->count());
        $bar->start();

        foreach ($episodes as $episode) {
            try {
                (new StorePodcastMedia($episode, (bool) $this->option('force')))->handle();
            } catch (Throwable $exception) {
                // Carry on rather than abandoning the run: one dead URL in the
                // back catalogue should not cost the other 254 downloads.
                $failed++;
                $this->newLine();
                $this->components->warn("{$episode->slug()}: {$exception->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($failed > 0) {
            $this->components->warn("{$failed} episode(s) failed. Re-run to retry just those.");
        }

        $this->components->info('Mirrored '.($episodes->count() - $failed).' episode(s).');

        return $failed === $episodes->count() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Episodes still missing a stored copy, oldest first so an interrupted
     * back-fill resumes where it left off rather than re-checking the newest.
     *
     * @return Collection<int, Podcast>
     */
    private function targetEpisodes(): Collection
    {
        $query = Podcast::query()
            ->with('media')
            ->whereNotNull('audio_url')
            ->orderBy('occurred_at');

        if (! $this->option('force')) {
            $query->whereDoesntHave('media', fn ($media) => $media->where('collection_name', 'audio'));
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        return $query->get();
    }
}
