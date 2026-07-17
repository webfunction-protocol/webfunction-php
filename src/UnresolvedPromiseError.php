<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * Raised when reading the value of a {@see Promise} that has not been resolved yet.
 */
class UnresolvedPromiseError extends Error
{
}
