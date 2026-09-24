<?php

namespace App\Enums;

/**
 * Which end of a span `occurred_at` marks. The other bound is `ends_at` for a
 * start-anchored dataset and `started_at` for an end-anchored one.
 */
enum SpanAnchor: string
{
    case Start = 'start';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Start',
            self::End => 'End',
        };
    }
}
