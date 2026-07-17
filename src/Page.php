<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A page of results from a paginated endpoint.
 *
 * Paginated responses follow the Web Function pagination contract: a JSON object with `page`, `next`, and
 * `previous` keys. {@see Request} detects this shape and returns a {@see Page} instead of a bare array. The
 * `next` and `previous` values are opaque request bodies — call {@see self::nextPage()} or
 * {@see self::previousPage()} to fetch the adjacent page; do not construct or modify them yourself.
 *
 * @example
 *   $page = Request::execute(
 *       "https://api.example.com/list-people",
 *       null, null,
 *       ["filters" => ["first_name" => "Joe"]]
 *   );
 *   $page->getItems();       // => [["person_id" => "...", ...], ...]
 *   $page->hasNext();        // => true
 *   $nextPage = $page->nextPage();
 *   $nextPage->hasPrevious(); // => true
 *
 * See the pagination specification for the full contract: https://webfunction.org/pagination
 *
 * @implements \IteratorAggregate<int, mixed>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /** @var array<int, mixed> */
    private $items;

    /** @var array<string, mixed>|null */
    private $nextBody;

    /** @var array<string, mixed>|null */
    private $previousBody;

    /** @var string */
    private $url;

    /** @var string|null */
    private $bearerAuth;

    /** @var string|null */
    private $version;

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed>|null $nextBody
     * @param array<string, mixed>|null $previousBody
     */
    public function __construct(
        array $items,
        ?array $nextBody,
        ?array $previousBody,
        string $url,
        ?string $bearerAuth = null,
        ?string $version = null
    ) {
        $this->items = $items;
        $this->nextBody = $nextBody;
        $this->previousBody = $previousBody;
        $this->url = $url;
        $this->bearerAuth = $bearerAuth;
        $this->version = $version;
    }

    /**
     * Whether the given decoded response matches the paginated response shape.
     *
     * @param mixed $response
     */
    public static function isPaginated($response): bool
    {
        if (!is_array($response) || array_is_list($response)) {
            return false;
        }

        if (!array_key_exists('page', $response) || !array_key_exists('next', $response) || !array_key_exists('previous', $response)) {
            return false;
        }

        if (!is_array($response['page']) || !array_is_list($response['page'])) {
            return false;
        }

        $nextOk = $response['next'] === null || (is_array($response['next']) && !array_is_list($response['next']));
        $previousOk = $response['previous'] === null || (is_array($response['previous']) && !array_is_list($response['previous']));

        return $nextOk && $previousOk;
    }

    /**
     * Wraps a paginated response in a {@see Page}, or returns `$response` unchanged.
     *
     * @param mixed $response
     * @return mixed
     */
    public static function wrap($response, Request $request)
    {
        if (!self::isPaginated($response)) {
            return $response;
        }

        return new self(
            $response['page'],
            $response['next'],
            $response['previous'],
            $request->getUrl(),
            $request->getBearerAuth(),
            $request->getVersion()
        );
    }

    /**
     * The items on the current page.
     *
     * @return array<int, mixed>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function hasNext(): bool
    {
        return $this->nextBody !== null;
    }

    public function hasPrevious(): bool
    {
        return $this->previousBody !== null;
    }

    /**
     * Fetches the next page by posting the opaque `next` body to the same endpoint.
     *
     * @return self|null
     */
    public function nextPage()
    {
        return $this->fetch($this->nextBody);
    }

    /**
     * Fetches the previous page by posting the opaque `previous` body to the same endpoint.
     *
     * @return self|null
     */
    public function previousPage()
    {
        return $this->fetch($this->previousBody);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return \ArrayIterator<int, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return self|null
     */
    private function fetch(?array $body)
    {
        if ($body === null) {
            return null;
        }

        $result = Request::execute($this->url, $this->bearerAuth, $this->version, $body);

        return $result instanceof self ? $result : null;
    }
}
