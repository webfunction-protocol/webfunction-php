<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A trait that provides a flaggable interface. Flags are used to define the behavior of an object.
 *
 * @example
 *   class Endpoint {
 *       use Flaggable;
 *
 *       public function __construct(array $flags = []) { $this->flags = $flags; }
 *   }
 *
 *   $endpoint = new Endpoint(['private']);
 *   $endpoint->hasFlag('private'); // => true
 *   $endpoint->hasFlag('public');  // => false
 */
trait Flaggable
{
    /** @var array<int, string> */
    protected $flags = [];

    /**
     * List of flags. See the [available flags section][1] on the Web Function website for a complete list.
     *
     * [1]: https://webfunction.org/package#available-flags
     *
     * @return array<int, string>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Whether the object declares the given flag.
     */
    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }
}
