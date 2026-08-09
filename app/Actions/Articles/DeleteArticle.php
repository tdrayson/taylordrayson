<?php

namespace App\Actions\Articles;

use App\Models\Article;

class DeleteArticle
{
    public function __invoke(Article $article): void
    {
        $article->delete();
    }
}
