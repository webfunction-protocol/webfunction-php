<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * An error definition as described in a Web Function package: a machine-readable error code paired with
 * human-readable documentation.
 *
 * See https://webfunction.org/package#error-definition for more details.
 */
final class DocumentedError
{
    /** @var string */
    private $code;

    /** @var string */
    private $docs;

    public function __construct(string $code, string $docs = '')
    {
        $this->code = $code;
        $this->docs = $docs;
    }

    /**
     * @param array<string, mixed> $error
     */
    public static function fromArray(array $error): ?self
    {
        if (!isset($error['code'])) {
            return null;
        }

        return new self(
            (string) $error['code'],
            (string) (isset($error['docs']) ? $error['docs'] : '')
        );
    }

    /**
     * @param array<int, mixed> $errors
     *
     * @return array<int, self>
     */
    public static function fromList(array $errors): array
    {
        return Utils::normalizeArray($errors, function ($error) {
            return is_array($error) ? self::fromArray($error) : null;
        });
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDocs(): string
    {
        return $this->docs;
    }
}
