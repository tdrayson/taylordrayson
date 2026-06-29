<?php

namespace App\Cp;

abstract class TimelineCpResource extends CpResource
{
    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return ['occurred_at', 'desc'];
    }
}
