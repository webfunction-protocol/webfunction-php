<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * Base exception class for WebFunction. All exceptions raised by this library extend this class.
 *
 * @api private
 */
class Error extends \RuntimeException
{
    /** @var string */
    protected $wfnCode;

    /** @var mixed */
    protected $details;

    /**
     * @param mixed $details
     */
    public function __construct($message = null, $code = null, $details = null)
    {
        parent::__construct($message !== null ? $message : '');

        $this->wfnCode = $code !== null ? $code : static::errorCode();
        $this->details = $details;
    }

    /**
     * The machine-readable Web Function error code (e.g. `USER_NOT_FOUND`, `WFN_BAD_REQUEST_ERROR`).
     *
     * Named `getErrorCode()` rather than `getCode()` because `Exception::getCode()` is `final` on PHP's
     * base exception classes and returns an `int`; this is the string equivalent, mirroring the Ruby gem's
     * `#code`.
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->wfnCode;
    }

    /**
     * @return mixed
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Derives the default error code for the exception class, e.g. `BadRequestError` becomes
     * `WFN_BAD_REQUEST_ERROR`.
     *
     * @return string
     */
    public static function errorCode()
    {
        $shortName = (new \ReflectionClass(static::class))->getShortName();
        $withUnderscores = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $shortName);

        return 'WFN_' . strtoupper($withUnderscores);
    }
}
