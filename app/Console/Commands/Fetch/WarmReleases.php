<?php

namespace App\Console\Commands\Fetch;

use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Services\GitHub\Client;
use App\Support\PortableText;
use App\Support\ReleaseCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('releases:warm {repo?* : Specific repos in owner/name form; defaults to every repo a file block points at}')]
#[Description('Refresh the cached latest release behind every GitHub file block in written content')]
class WarmReleases extends Command
{
    public function handle(Client $github): int
    {
        $repos = $this->argument('repo') ?: $this->referencedRepos();

        if ($repos === []) {
            $this->components->warn('No file blocks point at a GitHub release.');

            return self::SUCCESS;
        }

        $warmed = 0;
        $failed = 0;

        foreach ($repos as $repo) {
            $release = $github->latestRelease($repo);

            if ($release === null) {
                $this->components->warn("{$repo} - no release found");
                $failed++;

                continue;
            }

            ReleaseCache::put($repo, $release);
            $this->components->task("{$repo} {$release->version}");
            $warmed++;
        }

        $this->newLine();
        $this->components->info("Warmed {$warmed}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every repo referenced by a GitHub file block, across the three types whose
     * body is Portable Text.
     *
     * @return list<string>
     */
    private function referencedRepos(): array
    {
        $repos = [];

        foreach ([Article::class, Page::class, Note::class] as $class) {
            foreach ($class::query()->cursor() as $model) {
                foreach (PortableText::nodes($model->content) as $node) {
                    $repo = $node['repo'] ?? null;

                    if (($node['_type'] ?? null) === 'file' && ($node['source'] ?? null) === 'github' && is_string($repo)) {
                        $repos[$repo] = true;
                    }
                }
            }
        }

        return array_keys($repos);
    }
}
