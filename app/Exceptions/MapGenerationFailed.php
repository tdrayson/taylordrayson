<?php

namespace App\Exceptions;

use App\Actions\Concerns\FetchesMapImages;
use RuntimeException;

/**
 * Thrown by {@see FetchesMapImages} when a map image cannot be fetched. A map is
 * a light and a dark image together, so storing only one leaves an entry whose
 * map renders in neither theme; callers must fail closed instead.
 */
class MapGenerationFailed extends RuntimeException {}
