<?php

declare(strict_types=1);

namespace WebFunction\Type;

/**
 * The common interface implemented by every parsed type ({@see Base}, {@see ArrayOf}, {@see Union}, {@see Any}).
 */
interface TypeInterface
{
    public function getBaseType(): ?string;

    public function getRefinement(): ?string;

    /**
     * @param string $format One of `default`, `compact`, or `base`
     */
    public function format(string $format = 'default'): string;

    public function __toString(): string;

    public function equals(TypeInterface $other): bool;

    /**
     * The names of any `object.<name>` references contained within this type.
     *
     * @return array<int, string>
     */
    public function objects(): array;

    /**
     * Returns an equivalent type with any refinements stripped.
     */
    public function withoutRefinements(): TypeInterface;

    /**
     * Whether the given value matches this type (and its refinement, if any).
     *
     * @param mixed $value
     */
    public function isValid($value): bool;
}
