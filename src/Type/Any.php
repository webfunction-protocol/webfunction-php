<?php

declare(strict_types=1);

namespace WebFunction\Type;

/**
 * The `any` type. Matches every value. Used when a type could not be determined, e.g. an
 * empty or missing `returns`/`type` declaration.
 */
final class Any implements TypeInterface
{
    /** @var bool */
    private $param;

    /** @var string|null */
    private $malformed;

    /**
     * @param bool        $param     Whether this is the generic type parameter `T`. An unresolved parameter is
     *                               `any` to every consumer; the flag only lets tooling that expands generics
     *                               tell it from a literal `any`.
     * @param string|null $malformed One of the `Type::MALFORMED_*` reasons when this stands in for an invalid
     *                               generic application, so validation tooling can still report it.
     */
    public function __construct(bool $param = false, ?string $malformed = null)
    {
        $this->param = $param;
        $this->malformed = $malformed;
    }

    /**
     * Whether this is the generic type parameter `T`.
     */
    public function isParam(): bool
    {
        return $this->param;
    }

    /**
     * The `Type::MALFORMED_*` reason when this stands in for an invalid generic application, otherwise null.
     */
    public function getMalformed(): ?string
    {
        return $this->malformed;
    }

    public function getBaseType(): string
    {
        return 'any';
    }

    public function getRefinement(): ?string
    {
        return null;
    }

    public function format(string $format = 'default'): string
    {
        return 'any';
    }

    public function __toString(): string
    {
        return $this->format('default');
    }

    public function equals(TypeInterface $other): bool
    {
        return $other instanceof self;
    }

    public function objects(): array
    {
        return [];
    }

    public function withoutRefinements(): TypeInterface
    {
        return $this;
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        return true;
    }
}