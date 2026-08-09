<?php

namespace App\Actions\Pages;

use App\Models\Page;

class DeletePage
{
    public function __invoke(Page $page): void
    {
        $page->delete();
    }
}
