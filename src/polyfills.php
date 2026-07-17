<?php

declare(strict_types=1);

// Polyfills for functions introduced after PHP 7.3, so this library can run on 7.3+.
// Each is only defined if the running PHP version doesn't already provide it natively.

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $length = strlen($needle);

        return substr($haystack, -$length) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $i = 0;

        foreach ($array as $key => $value) {
            if ($key !== $i) {
                return false;
            }

            $i++;
        }

        return true;
    }
}
