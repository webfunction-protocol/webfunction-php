<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * Internal utility methods.
 *
 * @api private
 */
final class Utils
{
    private function __construct()
    {
    }

    /**
     * Normalizes a collection. Applies the given callback to each item if provided, otherwise returns the item
     * unchanged. Any `null` results are removed.
     *
     * @param mixed $collection The collection to normalize
     * @param callable|null $callback An optional mapping callback
     *
     * @return array<int, mixed>
     */
    public static function normalizeArray($collection, callable $callback = null): array
    {
        if (!is_array($collection)) {
            return [];
        }

        $items = array_map(function ($item) use ($callback) {
            return $callback ? $callback($item) : $item;
        }, $collection);

        return array_values(array_filter($items, function ($item) {
            return $item !== null;
        }));
    }

    /**
     * Normalizes an array of strings. Uses {@see self::normalizeArray()} under the hood.
     *
     * @param mixed $collection The collection to normalize
     *
     * @return array<int, string>
     */
    public static function normalizeArrayOfStrings($collection): array
    {
        return self::normalizeArray($collection, function ($item) {
            return (string) $item;
        });
    }

    /**
     * Fetches the raw body of a plain HTTP GET request, adding the given extra query parameters to the URL.
     *
     * @param string $url The URL to fetch
     * @param array<string, mixed> $extraQueryParams Extra query parameters to merge into the URL
     */
    public static function getBodyFromUrl(string $url, array $extraQueryParams = []): string
    {
        $url = self::addQueryParams($url, $extraQueryParams);

        $client = Request::getHttpClient();
        $response = $client($url, [
            'User-Agent' => 'webfunction-php/' . Version::VERSION,
            'Accept-Encoding' => 'gzip',
        ], null);

        return $response[1];
    }

    /**
     * Merges the given query parameters into a URL. `null` values are dropped, and existing query parameters
     * are preserved unless overwritten.
     *
     * @param string $url The URL to modify
     * @param array<string, mixed> $params The parameters to merge in
     */
    public static function addQueryParams(string $url, array $params = []): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $existingParams = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $existingParams);
        }

        $newParams = array_filter($params, function ($value) {
            return $value !== null;
        });

        if (!empty($newParams)) {
            $newParams = array_combine(
                array_map('strval', array_keys($newParams)),
                array_values($newParams)
            );
        }

        $mergedParams = array_merge($existingParams, $newParams);

        if (!empty($mergedParams)) {
            $parts['query'] = http_build_query($mergedParams);
        }

        return self::buildUrl($parts);
    }

    /**
     * @param array<string, mixed> $parts The parsed URL components, as returned by `parse_url()`
     */
    private static function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $userInfo = $user !== '' ? $user . $pass . '@' : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $userInfo . $host . $port . $path . $query . $fragment;
    }
}
