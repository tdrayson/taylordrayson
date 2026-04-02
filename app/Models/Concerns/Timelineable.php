<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

interface Timelineable
{
    /**
     * @return array{
     *     type: string,
     *     icon: string,
     *     title: string,
     *     subtitle: ?string,
     *     occurred_at: Carbon,
     *     accent: string,
     *     meta: array,
     * }
     */
    public function card(): array;

    public function slug(): string;

    public function url(): string;
}
