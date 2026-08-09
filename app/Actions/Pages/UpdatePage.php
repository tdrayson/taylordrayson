<?php

namespace App\Actions\Pages;

use App\Models\Page;

class UpdatePage
{
    /**
     * @param  array{title?: string, slug?: string, excerpt?: string|null, content?: array<int, mixed>, published?: bool}  $attributes
     */
    public function __invoke(Page $page, array $attributes): Page
    {
        $page->fill($attributes)->save();

        return $page->refresh();
    }
}
