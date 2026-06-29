<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\Page;

class PageResource extends CpResource
{
    public function model(): string
    {
        return Page::class;
    }

    public function slug(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'Page';
    }

    public function pluralLabel(): string
    {
        return 'Pages';
    }

    public function group(): string
    {
        return 'Content';
    }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return ['title', 'asc'];
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['title', 'slug'];
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'draft', 'label' => 'Draft'],
        ];
    }
}
