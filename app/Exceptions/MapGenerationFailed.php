<?php

namespace App\Exceptions;

use App\Actions\Concerns\FetchesMapImages;
use RuntimeException;

/**
 * Thrown by {@see FetchesMapImages} when a map image cannot be fetched, so a
 * caller fails closed rather than treating a half-generated entry as done.
 *
 * Each map is stored as a light and a dark image. The generators used to skip
 * past a failed style and store whichever one worked, which left entries with a
 * dark map and no light one. Nothing renders the dark image on its own, so the
 * map silently disappeared from the entry in both themes while every caller
 * believed the work had succeeded.
 */
class MapGenerationFailed extends RuntimeException {}
