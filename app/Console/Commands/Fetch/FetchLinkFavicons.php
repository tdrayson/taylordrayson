<?php

namespace App\Console\Commands\Fetch;

use App\Actions\Links\StoreFavicon;
use App\Enums\Source;
use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Support\Links;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('links:favicons {host?* : Specific hosts to fetch; defaults to every host linked from written content, plus the platforms responses arrive from} {--force : Re-download favicons that already exist}')]
#[Description('Download a favicon for every external host linked from articles, pages and notes, and for each response platform')]
class FetchLinkFavicons extends Command
{
    public function handle(StoreFavicon $storeFavicon): int
    {
        $hosts = $this->argument('host') ?: $this->linkedHosts();

        $downloaded = 0;
        $skipped = 0;
        $unavailable = 0;
        $failed = 0;

        foreach ($hosts as $host) {
            match ($storeFavicon($host, (bool) $this->option('force'))) {
                'skipped' => $skipped++,
                'saved' => [$this->components->task($host), $downloaded++],
                'unavailable' => [$this->components->warn("{$host} - no favicon available"), $unavailable++],
                default => [$this->components->error("{$host} - request failed"), $failed++],
            };
        }

        $this->newLine();
        $this->components->info("Downloaded {$downloaded}, skipped {$skipped}, unavailable {$unavailable}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every external host linked from a document, across the three types whose
     * body is Portable Text, plus the platforms syndicated responses come from.
     *
     * @return list<string>
     */
    private function linkedHosts(): array
    {
        $hosts = [];

        foreach (Source::cases() as $source) {
            if ($source->host() !== null) {
                $hosts[$source->host()] = true;
            }
        }

        foreach ([Article::class, Page::class, Note::class] as $class) {
            foreach ($class::query()->cursor() as $model) {
                foreach (Links::hostsIn($model->content) as $host) {
                    $hosts[$host] = true;
                }
            }
        }

        return array_keys($hosts);
    }
}
