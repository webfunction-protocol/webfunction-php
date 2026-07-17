<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * Raised when the server responds with a status code other than 200 or 400.
 */
class UnexpectedStatusCodeError extends Error
{
}
