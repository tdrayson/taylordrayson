<?php

namespace App\Actions\Articles;

use App\Models\Article;

class UpdateArticle
{
    /**
     * @param  array{title?: string, slug?: string, excerpt?: string|null, content?: array<int, mixed>, published?: bool, occurred_at?: string, timezone?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(Article $article, array $attributes): Article
    {
        if (array_key_exists('tags', $attributes)) {
            $article->syncTagNames($attributes['tags']);
            unset($attributes['tags']);
        }

        $article->fill($attributes)->save();

        return $article->refresh();
    }
}
