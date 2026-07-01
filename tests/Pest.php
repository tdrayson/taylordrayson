<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Snapshot the current set of flat-file collection entries on disk.
 * Returns absolute paths for all *.md and *.*.md files across
 * articles, notes, and pages.
 *
 * @return array<int, string>
 */
function snapshotContentFiles(): array
{
    $patterns = [
        base_path('content/collections/articles/*.md'),
        base_path('content/collections/articles/*.*.md'),
        base_path('content/collections/notes/*.md'),
        base_path('content/collections/notes/*.*.md'),
        base_path('content/collections/pages/*.md'),
        base_path('content/collections/pages/*.*.md'),
    ];

    $files = [];

    foreach ($patterns as $pattern) {
        $found = File::glob($pattern);
        if (is_array($found)) {
            $files = array_merge($files, $found);
        }
    }

    return array_unique($files);
}

/**
 * Delete only the flat-file entries that were NOT present in the given
 * pre-test snapshot, preserving any committed entries that existed before
 * the test ran.
 *
 * @param  array<int, string>  $preExisting
 */
function deleteNewContentFiles(array $preExisting): void
{
    $current = snapshotContentFiles();
    $toDelete = array_diff($current, $preExisting);

    if (count($toDelete) > 0) {
        File::delete(array_values($toDelete));
    }
}
