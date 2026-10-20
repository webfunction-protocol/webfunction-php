<?php

declare(strict_types=1);

namespace WebFunction\Type;

use WebFunction\Type as TypeFactory;

/**
 * A base type: `string`, `number`, `object`, `boolean`, or `null`, with an optional refinement
 * (e.g. `string.email`, `number.u32`).
 */
final class Base implements TypeInterface
{
    /** @var string */
    private $baseType;

    /** @var string|null */
    private $refinement;

    /** @var TypeInterface|null */
    private $arg;

    /**
     * @param TypeInterface|null $arg The type argument when this is a generic object applied to a type:
     *                                `{"object.list": "object.user"}` is `object.list` with the argument `object.user`.
     */
    public function __construct(string $baseType, ?string $refinement = null, ?TypeInterface $arg = null)
    {
        $this->baseType = $baseType;
        $this->refinement = $refinement;
        $this->arg = $arg;
    }

    /**
     * The type argument of a generic application, or null for any other type.
     */
    public function getArg(): ?TypeInterface
    {
        return $this->arg;
    }

    public function getBaseType(): string
    {
        return $this->baseType;
    }

    public function getRefinement(): ?string
    {
        return $this->refinement;
    }

    public function format(string $format = 'default'): string
    {
        switch ($format) {
            case 'compact':
                return $this->refinement !== null ? $this->refinement : $this->baseType;
            case 'base':
                return $this->baseType;
            case 'default':
                $text = $this->refinement !== null
                    ? "{$this->baseType}.{$this->refinement}"
                    : $this->baseType;

                return $this->arg !== null ? $text . '<' . $this->arg->format($format) . '>' : $text;
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
        return $other instanceof self
            && $other->baseType === $this->baseType
            && $other->refinement === $this->refinement
            && ($this->arg === null ? $other->arg === null : ($other->arg !== null && $this->arg->equals($other->arg)));
    }

    public function objects(): array
    {
        $objects = [];

        if ($this->baseType === 'object' && $this->refinement !== null) {
            $objects[] = $this->refinement;
        }

        if ($this->arg !== null) {
            foreach ($this->arg->objects() as $object) {
                if (!in_array($object, $objects, true)) {
                    $objects[] = $object;
                }
            }
        }

        return $objects;
    }

    public function withoutRefinements(): TypeInterface
    {
        if ($this->refinement === null) {
            return $this;
        }

        return new self($this->baseType);
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        switch ($this->baseType) {
            case 'string':
                return is_string($value) && $this->refinementValid($value);
            case 'number':
                return (is_int($value) || is_float($value)) ? $this->refinementValid($value) : false;
            case 'object':
                return is_array($value) || $value instanceof \stdClass;
            case 'boolean':
                return is_bool($value);
            case 'null':
                return $value === null;
            default:
                return false;
        }
    }

    /**
     * @param mixed $value
     */
    private function refinementValid($value): bool
    {
        if ($this->refinement === null) {
            return true;
        }

        return TypeFactory::validateRefinement($this->refinement, $value);
    }
}