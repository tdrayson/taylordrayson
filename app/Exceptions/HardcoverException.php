<?php

namespace App\Exceptions;

use App\Services\Hardcover;
use RuntimeException;

/**
 * Thrown by {@see Hardcover} when a GraphQL request fails outright
 * (HTTP error or GraphQL `errors` payload), so callers fail closed
 * instead of treating a broken response as empty search results.
 */
class HardcoverException extends RuntimeException {}
