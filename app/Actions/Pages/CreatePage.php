<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Str;

class CreatePage
{
    /**
     * A page is routed by slug rather than by date, so the slug is the one
     * thing it cannot do without; left blank it comes from the title.
     *
     * New pages start unpublished, so writing one never puts a half-finished
     * /about in front of anyone.
     *
     * @param  array{title: string, slug?: string|null, excerpt?: string|null, content?: array<int, mixed>|null, published?: bool}  $attributes
     */
    public function __invoke(array $attributes): Page
    {
        $slug = $attributes['slug'] ?? null;

        return Page::create([
            'title' => $attributes['title'],
            'slug' => $slug !== null && $slug !== '' ? $slug : Str::slug($attributes['title']),
            'excerpt' => $attributes['excerpt'] ?? null,
            'content' => $attributes['content'] ?? [],
            'published' => $attributes['published'] ?? false,
        ]);
    }
}
