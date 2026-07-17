<?php

declare(strict_types=1);

namespace WebFunction\Type;

/**
 * A union of several types (e.g. `object | null`).
 */
final class Union implements TypeInterface
{
    /** @var array<int, TypeInterface> */
    private $members;

    /**
     * @param array<int, TypeInterface> $members
     */
    public function __construct(array $members)
    {
        $this->members = $members;
    }

    /**
     * @return array<int, TypeInterface>
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    public function getBaseType(): ?string
    {
        return null;
    }

    public function getRefinement(): ?string
    {
        return null;
    }

    public function format(string $format = 'default'): string
    {
        return implode(' | ', array_map(function (TypeInterface $member) use ($format) {
            return $member->format($format);
        }, $this->members));
    }

    public function __toString(): string
    {
        return $this->format('default');
    }

    public function equals(TypeInterface $other): bool
    {
        if (!$other instanceof self || count($other->members) !== count($this->members)) {
            return false;
        }

        foreach ($this->members as $i => $member) {
            if (!$member->equals($other->members[$i])) {
                return false;
            }
        }

        return true;
    }

    public function objects(): array
    {
        $objects = [];

        foreach ($this->members as $member) {
            foreach ($member->objects() as $object) {
                $objects[$object] = true;
            }
        }

        return array_keys($objects);
    }

    public function withoutRefinements(): TypeInterface
    {
        return \WebFunction\Type::union(array_map(function (TypeInterface $member) {
            return $member->withoutRefinements();
        }, $this->members));
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        foreach ($this->members as $member) {
            if ($member->isValid($value)) {
                return true;
            }
        }

        return false;
    }
}
