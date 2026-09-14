<?php

namespace App\Actions\Books;

use App\Enums\EntryStatus;
use App\Enums\Source;
use App\Models\Book;
use App\Support\BookCompleteness;
use App\Support\BookProgress;
use App\Support\EntryInstant;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Turn a page into percent, publish a finished book, and refuse to publish one
 * missing its title, author or cover.
 */
final class PrepareBookSave
{
    private const SYNCED = ['progress_percent', 'current_page', 'pages'];

    /**
     * @param  array<string, mixed>  $attributes  Validated editor values, with remote media already fetched.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function __invoke(Book $book, array $attributes): array
    {
        $attributes = $book->source === Source::Kindle->value
            ? Arr::except($attributes, self::SYNCED)
            : $this->progressFromPage($book, $attributes);

        $leavingDraft = ! $book->exists || $book->status === EntryStatus::Draft;
        $status = EntryStatus::tryFrom((string) ($attributes['status'] ?? '')) ?? $book->status;
        $missing = $this->missing($book, $attributes);
        $percent = $attributes['progress_percent'] ?? $book->progress_percent;

        if ($status === EntryStatus::Draft && $percent !== null && $percent >= BookProgress::FINISHED && $missing === []) {
            $status = EntryStatus::Published;
            $attributes['status'] = $status->value;
        }

        if ($status === EntryStatus::Draft || ! $leavingDraft) {
            return $attributes;
        }

        if ($missing !== []) {
            throw ValidationException::withMessages(['status' => 'Add '.BookCompleteness::sentence($missing).' before publishing.']);
        }

        $finished = $attributes['progressed_at'] ?? $book->progressed_at?->format('Y-m-d H:i:s');

        if (blank($attributes['occurred_at'] ?? null) && $book->occurred_at === null && $finished !== null) {
            $attributes['occurred_at'] = $finished;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function missing(Book $book, array $attributes): array
    {
        return BookCompleteness::missing(
            Arr::has($attributes, 'title') ? Arr::get($attributes, 'title') : $book->title,
            Arr::has($attributes, 'meta.author') ? Arr::get($attributes, 'meta.author') : $book->meta?->author,
            array_key_exists('cover', $attributes)
                ? filled($attributes['cover'])
                : $book->exists && $book->hasMedia('cover'),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function progressFromPage(Book $book, array $attributes): array
    {
        if (! array_key_exists('current_page', $attributes) && ! array_key_exists('pages', $attributes)) {
            return $attributes;
        }

        $page = $attributes['current_page'] ?? $book->current_page;
        $pages = array_key_exists('pages', $attributes) ? $attributes['pages'] : $book->pages;

        if (blank($page)) {
            return $attributes;
        }

        if (blank($pages) || (int) $pages < 1) {
            throw ValidationException::withMessages(['pages' => 'Add the page count to track by page.']);
        }

        $percent = BookProgress::fromPage((int) $page, (int) $pages);

        if ($percent === $book->progress_percent) {
            return $attributes;
        }

        return [
            ...$attributes,
            'progress_percent' => $percent,
            'progressed_at' => EntryInstant::nowLocal($attributes['timezone'] ?? $book->timezone)->format('Y-m-d H:i:s'),
        ];
    }
}
