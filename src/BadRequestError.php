<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * Raised when the server responds with a 400 status code. Carries the error triple's code, message, and
 * details when the response body matches the error triple shape (`[code, message, details]`).
 */
class BadRequestError extends Error
{
}
