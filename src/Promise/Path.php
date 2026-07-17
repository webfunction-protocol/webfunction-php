<?php

declare(strict_types=1);

namespace WebFunction\Promise;

/**
 * A JSONPath expression referencing a value inside a not-yet-resolved {@see \WebFunction\Promise} result.
 * Indexing into a {@see \WebFunction\Promise} before it resolves returns one of these instead of a value;
 * when the path is serialized into a pipeline step's JSON body, the server resolves it against the actual
 * result of an earlier step.
 *
 * @example
 *   $path = new Path("$[0]");
 *   (string) $path;   // => "$[0]"
 *   $path["a"];       // => Path("$[0].a")
 */
final class Path implements \JsonSerializable, \ArrayAccess
{
    /** @var string */
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->path;
    }

    /**
     * Returns a new path with the given key or index appended.
     *
     * @param string|int $key
     */
    public function get($key): self
    {
        if (is_int($key)) {
            return new self("{$this->path}[{$key}]");
        }

        return new self("{$this->path}.{$key}");
    }

    public function offsetExists($offset)
    {
        return true;
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Promise paths are read-only');
    }

    public function offsetUnset($offset)
    {
        throw new \LogicException('Promise paths are read-only');
    }
}
