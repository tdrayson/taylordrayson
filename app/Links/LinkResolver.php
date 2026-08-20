<?php

namespace App\Links;

use App\Data\LinkPreviewData;

/**
 * Turns one internal path into the card shown when hovering a link to it.
 * Implement this per kind of destination and register it in {@see LinkResolvers}.
 */
interface LinkResolver
{
    /**
     * The preview for this path, or null when this resolver does not own it.
     */
    public function resolve(string $path): ?LinkPreviewData;
}
