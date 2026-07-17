<?php

declare(strict_types=1);

namespace WebFunction;

use WebFunction\Promise\Path;

/**
 * A promise stands in for a pipelined call's result before the pipeline has run. Indexing into an
 * unresolved promise (`$promise['id']`) returns a {@see Path} — a JSONPath reference the server resolves
 * against the real result once the pipeline executes. Once resolved, the same indexing returns the real
 * value.
 *
 * @example
 *   $pipeline = new Pipeline("https://pipe.example/exec");
 *   $promise = $pipeline->addStep([]);
 *   $promise->resolve(); // => ["a" => 1]
 */
final class Promise implements \JsonSerializable, \ArrayAccess
{
    /** @var Pipeline */
    private $pipeline;

    /** @var Path */
    private $path;

    /** @var mixed */
    private $value = null;

    /** @var bool */
    private $resolved = false;

    public function __construct(Pipeline $pipeline, string $path)
    {
        $this->pipeline = $pipeline;
        $this->path = new Path($path);
    }

    /**
     * Sets the resolved value of the promise. Called by {@see Pipeline} once the batch has run.
     *
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
        $this->resolved = true;
    }

    public function __toString(): string
    {
        return $this->resolved ? (string) json_encode($this->value) : (string) $this->path;
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->resolved ? $this->value : $this->path;
    }

    public function offsetExists($offset)
    {
        return true;
    }

    /**
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->resolved) {
            return is_array($this->value) && isset($this->value[$offset]) ? $this->value[$offset] : null;
        }

        return $this->path->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Promises are read-only');
    }

    public function offsetUnset($offset)
    {
        throw new \LogicException('Promises are read-only');
    }

    /**
     * Returns the resolved value.
     *
     * @return mixed
     *
     * @throws UnresolvedPromiseError If the promise has not been resolved yet.
     */
    public function value()
    {
        if (!$this->resolved) {
            throw new UnresolvedPromiseError();
        }

        return $this->value;
    }

    /**
     * Resolves the promise, running the owning pipeline if it has not already run.
     *
     * @return mixed
     */
    public function resolve()
    {
        if ($this->resolved) {
            return $this->value;
        }

        $this->pipeline->execute();

        return $this->value();
    }
}
