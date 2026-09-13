<?php

namespace App\Actions\Articles;

use App\Enums\EntryStatus;
use App\Models\Article;
use App\Support\EntryInstant;
use App\Support\TimelineUrlSlug;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateArticle
{
    /**
     * Articles start as drafts unless a status is given, so writing one never
     * lists it by accident.
     *
     * @param  array{title: string, slug?: string|null, excerpt?: string|null, content?: array<int, mixed>|null, status?: string, password?: string|null, occurred_at?: string|null, timezone?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(array $attributes): Article
    {
        $slug = $attributes['slug'] ?? null;
        $slug = $slug !== null && $slug !== '' ? $slug : Str::slug($attributes['title']);

        if (TimelineUrlSlug::isReserved($slug)) {
            throw ValidationException::withMessages(['slug' => [TimelineUrlSlug::reservationMessage($slug)]]);
        }

        $article = Article::create([
            'title' => $attributes['title'],
            'slug' => $slug,
            'excerpt' => $attributes['excerpt'] ?? null,
            'content' => $attributes['content'] ?? [],
            'status' => $attributes['status'] ?? EntryStatus::Draft,
            'password' => $attributes['password'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? EntryInstant::nowLocal(),
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
        ]);

        if (array_key_exists('tags', $attributes)) {
            $article->syncTagNames($attributes['tags']);
        }

        return $article;
    }
}
