<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Appearance;

class AppearanceResource extends TimelineCpResource
{
    public function model(): string
    {
        return Appearance::class;
    }

    public function slug(): string
    {
        return 'appearances';
    }

    public function label(): string
    {
        return 'Appearance';
    }

    public function pluralLabel(): string
    {
        return 'Appearances';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['title', 'show_name'];
    }
}
