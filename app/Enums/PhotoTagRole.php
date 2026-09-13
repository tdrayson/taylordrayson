<?php

namespace App\Enums;

/**
 * What a photo tag claims. A subject is in the picture at a point; a camera
 * took it and has no position, so x and y are null for one.
 */
enum PhotoTagRole: string
{
    case Subject = 'subject';
    case Camera = 'camera';

    public function needsPosition(): bool
    {
        return $this === self::Subject;
    }
}
