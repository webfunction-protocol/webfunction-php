<?php

declare(strict_types=1);

namespace WebFunction\Type;

/**
 * The `any` type. Matches every value. Used when a type could not be determined, e.g. an
 * empty or missing `returns`/`type` declaration.
 */
final class Any implements TypeInterface
{
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
