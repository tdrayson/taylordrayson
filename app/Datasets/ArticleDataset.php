<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Article;
use App\Presenters\Cards\ArticleCard;
use App\Presenters\Exports\ArticleExport;
use App\Timeline\Taxonomies;

/**
 * Long-form articles and blog posts.
 */
final class ArticleDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Article;
    }

    public function model(): string
    {
        return Article::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Writing;
    }

    public function icon(): string
    {
        return 'File01Icon';
    }

    public function label(): string
    {
        return 'Article';
    }

    public function plural(): string
    {
        return 'Articles';
    }

    public function slug(): string
    {
        return 'articles';
    }

    public function keywords(): string
    {
        return 'blog post writing read';
    }

    public function card(): ArticleCard
    {
        return new ArticleCard;
    }

    public function export(): object
    {
        return new ArticleExport;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'dataType' => 'text', 'column' => 'title', 'category' => 'Article'],
            'excerpt' => ['label' => 'Excerpt', 'dataType' => 'text', 'column' => 'excerpt', 'category' => 'Article'],
            'content' => ['label' => 'Content', 'dataType' => 'text', 'column' => 'content', 'category' => 'Article'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['title', 'excerpt', 'content'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::tags(fn (string $label): string => "Articles tagged {$label}");
    }

    public function draftable(): bool
    {
        return true;
    }
}
