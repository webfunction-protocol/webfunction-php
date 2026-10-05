<?php

declare(strict_types=1);

namespace WebFunction;

use WebFunction\Type\TypeInterface;

/**
 * An attribute describes one output field that may appear in an {@see Endpoint}'s response (or, in an
 * attribute context, one field of an {@see ObjectSchema}), when the return type is `object`.
 *
 * See https://webfunction.org/package#attributes for more details.
 */
final class Attribute
{
    use Flaggable;

    /** @var string */
    private $name;

    /** @var TypeInterface */
    private $type;

    /** @var array<int, mixed> */
    private $values;

    /** @var string */
    private $docs;

    /**
     * @param array<int, mixed> $values
     * @param array<int, string> $flags
     */
    public function __construct(
        string $name,
        TypeInterface $type,
        array $values = [],
        array $flags = [],
        string $docs = ''
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->values = $values;
        $this->flags = $flags;
        $this->docs = $docs;
    }

    /**
     * @param array<string, mixed> $attribute
     */
    public static function fromArray(array $attribute): ?self
    {
        if (!isset($attribute['name'], $attribute['type'])) {
            return null;
        }

        return new self(
            (string) $attribute['name'],
            Type::parse($attribute['type']),
            is_array(isset($attribute['values']) ? $attribute['values'] : null) ? array_values($attribute['values']) : [],
            Utils::normalizeArrayOfStrings(isset($attribute['flags']) ? $attribute['flags'] : []),
            (string) (isset($attribute['docs']) ? $attribute['docs'] : '')
        );
    }

    /**
     * @param array<int, mixed> $attributes
     *
     * @return array<int, self>
     */
    public static function fromList(array $attributes): array
    {
        return Utils::normalizeArray($attributes, function ($attribute) {
            return is_array($attribute) ? self::fromArray($attribute) : null;
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

    /**
     * @return array<int, mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getDocs(): string
    {
        return $this->docs;
    }

    public function isNullable(): bool
    {
        return $this->hasFlag('nullable');
    }

    public function isPrivate(): bool
    {
        return $this->hasFlag('private');
    }
}