<?php

namespace App\Exceptions;

use App\Services\Trakt;
use RuntimeException;

/**
 * Thrown by {@see Trakt} when a paginated fetch (watch history
 * or ratings) fails outright, so callers fail closed instead of silently
 * treating a failed response the same as an empty page.
 */
class TraktException extends RuntimeException {}
