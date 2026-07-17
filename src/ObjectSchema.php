<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A named object definition declared under a package's `"objects"` key. Any type can reference it as
 * `object.<name>`, so several endpoints can share the same shape instead of repeating its fields.
 *
 * An `object.` reference appears in one of two contexts, which determines which member set applies:
 *
 * - **Argument context** (an argument's `type`) — {@see self::getArguments()} describes its fields.
 * - **Attribute context** (an endpoint's `returns`, or an attribute's `type`) — {@see self::getAttributes()}
 *   describes its fields.
 *
 * An object may define both sets, since it may be referenced in both contexts within the same package.
 *
 * Named `ObjectSchema` rather than `Object` to avoid clashing with PHP's reserved words / built-ins.
 *
 * See https://webfunction.org/package#object-definition for more details.
 */
final class ObjectSchema
{
    const CONTEXT_ARGUMENTS = 'arguments';
    const CONTEXT_ATTRIBUTES = 'attributes';

    const CONTEXTS = [self::CONTEXT_ARGUMENTS, self::CONTEXT_ATTRIBUTES];

    /** @var string */
    private $name;

    /** @var array<string, Argument> */
    private $arguments;

    /** @var array<string, Attribute> */
    private $attributes;

    /**
     * @param array<int, Argument> $arguments
     * @param array<int, Attribute> $attributes
     */
    public function __construct(string $name, array $arguments = [], array $attributes = [])
    {
        $this->name = $name;

        $this->arguments = [];

        foreach ($arguments as $argument) {
            $this->arguments[$argument->getName()] = $argument;
        }

        $this->attributes = [];

        foreach ($attributes as $attribute) {
            $this->attributes[$attribute->getName()] = $attribute;
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    public static function fromArray(array $object): ?self
    {
        if (!isset($object['name'])) {
            return null;
        }

        return new self(
            (string) $object['name'],
            Argument::fromList(isset($object['arguments']) ? $object['arguments'] : []),
            Attribute::fromList(isset($object['attributes']) ? $object['attributes'] : [])
        );
    }

    /**
     * @param array<int, mixed> $objects
     *
     * @return array<int, self>
     */
    public static function fromList(array $objects): array
    {
        return Utils::normalizeArray($objects, function ($object) {
            return is_array($object) ? self::fromArray($object) : null;
        });
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, Argument>
     */
    public function getArguments(): array
    {
        return array_values($this->arguments);
    }

    public function getArgument(string $name): ?Argument
    {
        return isset($this->arguments[$name]) ? $this->arguments[$name] : null;
    }

    /**
     * @return array<int, Attribute>
     */
    public function getAttributes(): array
    {
        return array_values($this->attributes);
    }

    public function getAttribute(string $name): ?Attribute
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    /**
     * The object's properties for the given context.
     *
     * @param self::CONTEXT_* $context
     *
     * @return array<int, Argument>|array<int, Attribute>
     */
    public function properties(string $context): array
    {
        switch ($context) {
            case self::CONTEXT_ARGUMENTS:
                return $this->getArguments();
            case self::CONTEXT_ATTRIBUTES:
                return $this->getAttributes();
            default:
                throw new \InvalidArgumentException(
                    'context must be one of ' . implode(', ', self::CONTEXTS) . ", got {$context}"
                );
        }
    }
}
