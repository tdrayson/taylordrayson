<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Article;

class ArticleResource extends TimelineCpResource
{
    public function model(): string
    {
        return Article::class;
    }

    public function slug(): string
    {
        return 'articles';
    }

    public function label(): string
    {
        return 'Article';
    }

    public function pluralLabel(): string
    {
        return 'Articles';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['title', 'excerpt'];
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Date'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'draft', 'label' => 'Draft'],
        ];
    }

    /** @return array<int, array{area: string, tab?: string, title?: string, fields: array<int, string>}> */
    public function sections(): array
    {
        return [
            ['area' => 'main', 'fields' => ['title', 'excerpt', 'content']],
            ['area' => 'sidebar', 'title' => 'Publish', 'fields' => ['draft', 'occurred_at', 'slug', 'tags']],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function fieldOverrides(): array
    {
        return [
            'tags' => ['type' => 'tags'],
        ];
    }
}
