<?php

declare(strict_types=1);

namespace WebFunction\Type;

/**
 * An array type, parameterized by the type of its elements (e.g. `array<string>`).
 */
final class ArrayOf implements TypeInterface
{
    /** @var TypeInterface */
    private $of;

    public function __construct(TypeInterface $of)
    {
        $this->of = $of;
    }

    public function getBaseType(): string
    {
        return 'array';
    }

    public function getRefinement(): ?string
    {
        return null;
    }

    public function getOf(): TypeInterface
    {
        return $this->of;
    }

    public function format(string $format = 'default'): string
    {
        switch ($format) {
            case 'base':
                return 'array';
            case 'compact':
            case 'default':
                return 'array<' . $this->of->format($format) . '>';
            default:
                throw new \InvalidArgumentException("unknown format: {$format}");
        }
    }

    public function __toString(): string
    {
        return $this->format('default');
    }

    public function equals(TypeInterface $other): bool
    {
        return $other instanceof self && $other->of->equals($this->of);
    }

    public function objects(): array
    {
        return $this->of->objects();
    }

    public function withoutRefinements(): TypeInterface
    {
        return new self($this->of->withoutRefinements());
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $element) {
            if (!$this->of->isValid($element)) {
                return false;
            }
        }

        return true;
    }
}
