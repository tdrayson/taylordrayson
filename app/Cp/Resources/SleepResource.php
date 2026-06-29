<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Sleep;

class SleepResource extends TimelineCpResource
{
    public function model(): string
    {
        return Sleep::class;
    }

    public function slug(): string
    {
        return 'sleep';
    }

    public function label(): string
    {
        return 'Sleep';
    }

    public function pluralLabel(): string
    {
        return 'Sleep';
    }
}
