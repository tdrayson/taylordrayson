<?php

namespace App\Console\Commands\Import;

use App\Actions\Snippetclub\ImportPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * The one-time move of snippetclub.com into articles.
 *
 * Reads the export this app already holds under storage/app/snippetclub, which
 * `--fetch` refreshes from the temporary endpoint installed on the old site.
 * Delete this command, its actions and that endpoint once the content has moved.
 */
#[Signature('snippetclub:import {--dry-run : Report what each post converts to without writing} {--fetch : Re-download the export first} {--limit=0 : Stop after this many posts (0 = all)}')]
#[Description('Import the SnippetClub archive into articles')]
class SnippetclubImport extends Command
{
    private const DIRECTORY = 'snippetclub';

    public function handle(ImportPost $import): int
    {
        if ($this->option('fetch') && ! $this->fetch()) {
            return self::FAILURE;
        }

        $posts = $this->posts();

        if ($posts === []) {
            $this->components->error('No export found in storage/app/'.self::DIRECTORY.'. Run again with --fetch.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $updated = 0;
        $nodes = 0;
        $notes = [];

        foreach ($limit > 0 ? array_slice($posts, 0, $limit) : $posts as $post) {
            $result = $import($post, $dryRun);

            $nodes += $result->nodes;
            $result->created ? $created++ : $updated++;

            foreach ($result->notes as $note) {
                $notes[] = $result->slug.': '.$note;
            }

            $this->components->task(sprintf('%s (%d nodes, %d tags)', $result->slug, $result->nodes, count($result->tags)));
        }

        $this->newLine();

        foreach ($notes as $note) {
            $this->components->warn($note);
        }

        $this->components->info(sprintf(
            '%s %d posts into %d nodes. %d new, %d already here, %d flagged for review.',
            $dryRun ? 'Would import' : 'Imported',
            $created + $updated,
            $nodes,
            $created,
            $updated,
            count($notes),
        ));

        return self::SUCCESS;
    }

    /**
     * Every post across the paged export files.
     *
     * @return list<array<string, mixed>>
     */
    private function posts(): array
    {
        $posts = [];

        foreach (File::glob(storage_path('app/'.self::DIRECTORY.'/posts_*.json')) as $path) {
            $page = json_decode(File::get($path), true);

            foreach ($page['items'] ?? [] as $item) {
                $posts[] = $item;
            }
        }

        return $posts;
    }

    /** Re-download the export, one file per page, exactly as it is cached. */
    private function fetch(): bool
    {
        $url = config('services.snippetclub.url');
        $key = config('services.snippetclub.key');

        if (blank($url) || blank($key)) {
            $this->components->error('SNIPPETCLUB_URL and SNIPPETCLUB_KEY are not set.');

            return false;
        }

        $directory = storage_path('app/'.self::DIRECTORY);
        File::ensureDirectoryExists($directory);

        for ($page = 1; ; $page++) {
            $response = Http::api()->get($url.'/wp-json/snippetclub/v1/content', [
                'key' => $key,
                'type' => 'post',
                'page' => $page,
                'per_page' => 25,
            ]);

            if (! $response->successful()) {
                $this->components->error('Export request failed on page '.$page.': '.$response->status());

                return false;
            }

            File::put($directory.'/posts_'.$page.'.json', $response->body());
            $this->components->task('fetched page '.$page.' of '.$response->json('total_pages'));

            if ($page >= (int) $response->json('total_pages')) {
                return true;
            }
        }
    }
}
