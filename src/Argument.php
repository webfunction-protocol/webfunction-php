<?php

declare(strict_types=1);

namespace WebFunction;

use WebFunction\Type\TypeInterface;

/**
 * An argument describes one input parameter of an {@see Endpoint} (or, in an argument context, one field
 * of an {@see ObjectSchema}).
 *
 * See https://webfunction.org/package#arguments for more details.
 */
final class Argument
{
    use Flaggable;

    /** @var string */
    private $name;

    /** @var TypeInterface */
    private $type;

    /** @var string|null */
    private $group;

    /** @var array<int, mixed> */
    private $choices;

    /** @var string */
    private $docs;

    /**
     * @param array<int, mixed> $choices
     * @param array<int, string> $flags
     */
    public function __construct(
        string $name,
        TypeInterface $type,
        ?string $group = null,
        array $choices = [],
        array $flags = [],
        string $docs = ''
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->group = $group;
        $this->choices = $choices;
        $this->flags = $flags;
        $this->docs = $docs;
    }

    /**
     * @param array<string, mixed> $argument
     */
    public static function fromArray(array $argument): ?self
    {
        if (!isset($argument['name'], $argument['type'])) {
            return null;
        }

        return new self(
            (string) $argument['name'],
            Type::parse($argument['type']),
            isset($argument['group']) ? (string) $argument['group'] : null,
            is_array(isset($argument['choices']) ? $argument['choices'] : null) ? array_values($argument['choices']) : [],
            Utils::normalizeArrayOfStrings(isset($argument['flags']) ? $argument['flags'] : []),
            (string) (isset($argument['docs']) ? $argument['docs'] : '')
        );
    }

    /**
     * @param array<int, mixed> $arguments
     *
     * @return array<int, self>
     */
    public static function fromList(array $arguments): array
    {
        return Utils::normalizeArray($arguments, function ($argument) {
            return is_array($argument) ? self::fromArray($argument) : null;
        });
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @return array<int, mixed>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    public function getDocs(): string
    {
        return $this->docs;
    }

    public function isRequired(): bool
    {
        return $this->hasFlag('required');
    }

    public function isOptional(): bool
    {
        return !$this->isRequired();
    }

    public function isPrivate(): bool
    {
        return $this->hasFlag('private');
    }
}