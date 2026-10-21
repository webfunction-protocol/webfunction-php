<?php

declare(strict_types=1);

namespace WebFunction;

use WebFunction\Type\Any;
use WebFunction\Type\ArrayOf;
use WebFunction\Type\Base;
use WebFunction\Type\TypeInterface;
use WebFunction\Type\Union;

/**
 * Parses the type declarations used in a Web Function package (an endpoint's `returns`, an argument's
 * `type`, or an attribute's `type`) into a {@see TypeInterface} tree, and validates values against it.
 *
 * @example
 *   Type::parse(['object', 'null'])->__toString();  // => "object | null"
 *   Type::parse([['string']])->__toString();         // => "array<string>"
 *   Type::parse('array')->__toString();               // => "array<any>"
 *   Type::parse(null)->__toString();                  // => "any"
 */
final class Type
{
    /**
     * @var array<string, array<int, string>>
     */
    const ALLOWED_REFINEMENTS = [
        'number' => ['u32', 'u64', 'i32', 'i64', 'f32', 'f64', 'timestamp'],
        'string' => ['date', 'time', 'datetime', 'uuid', 'base64', 'email', 'phone', 'url', 'uri', 'ipv4', 'ipv6', 'hostname'],
    ];

    /** Reason recorded by {@see Any::getMalformed()}: a generic application object did not have exactly one key. */
    const MALFORMED_KEY_COUNT = 'key-count';

    /** Reason recorded by {@see Any::getMalformed()}: the application's key was neither `object.<name>` nor `array`. */
    const MALFORMED_KEY_PREFIX = 'key-prefix';

    /** Reason recorded by {@see Any::getMalformed()}: the application's value was not a valid type. */
    const MALFORMED_ARGUMENT = 'argument';

    private function __construct()
    {
    }

    /**
     * Parses a raw type declaration (a string, an array of strings/arrays, an application, or `null`)
     * into a {@see TypeInterface}. A top-level array is read as a union (a union of one entry is that entry);
     * a nested array denotes an array of the inner type. A generic application, `{"object.list": "object.user"}`
     * (an associative array once decoded), is a generic object applied to a type, and `{"array": "string"}`
     * is an array of that type, the same as `[["string"]]`. An empty or unrecognized declaration resolves to
     * {@see Any}, as does the bare type parameter `T` and any malformed application.
     *
     * @param mixed $raw
     */
    public static function parse($raw): TypeInterface
    {
        if (self::isApplication($raw)) {
            return self::application($raw);
        }

        $items = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);

        $types = array_values(array_filter(
            array_map(function ($type) {
                return self::detect($type);
            }, $items),
            function ($t) {
                return $t !== null;
            }
        ));

        if (empty($types)) {
            return self::any();
        }

        return self::union($types);
    }

    /**
     * @internal
     * @param mixed $raw
     */
    public static function detect($raw): ?TypeInterface
    {
        if (is_string($raw)) {
            return self::base($raw);
        }

        if (self::isApplication($raw)) {
            return self::application($raw);
        }

        if (is_array($raw)) {
            $types = array_values(array_filter(
                array_map(function ($type) {
                    return self::detect($type);
                }, $raw),
                function ($t) {
                    return $t !== null;
                }
            ));

            if (empty($types)) {
                return self::array(self::any());
            }

            return self::array(self::union($types));
        }

        return null;
    }

    /**
     * @internal
     */
    public static function base(string $type): ?TypeInterface
    {
        if ($type === 'T') {
            return new Any(true);
        }

        $parts = array_pad(explode('.', $type, 2), 2, null);
        $baseType = $parts[0];
        $refinement = $parts[1];

        switch ($baseType) {
            case 'string':
                return self::string(self::allowedRefinement('string', $refinement));
            case 'number':
                return self::number(self::allowedRefinement('number', $refinement));
            case 'object':
                return self::object($refinement);
            case 'array':
                return self::array();
            case 'boolean':
                return self::boolean();
            case 'null':
                return self::null();
            case 'any':
                return self::any();
            default:
                return null;
        }
    }

    /**
     * A generic application is a non-empty associative array (a JSON object once decoded). An empty JSON
     * object decodes to the same empty array as `[]`, so it can't be told apart and resolves to any.
     *
     * @param mixed $raw
     */
    private static function isApplication($raw): bool
    {
        return is_array($raw) && $raw !== [] && !array_is_list($raw);
    }

    /**
     * Parses a type application: a generic object applied to a type, `{"object.<name>": <type>}`, or an array of
     * a type, `{"array": <type>}` (the same as `[[<type>]]`). Anything that is not exactly one of those shapes
     * resolves to {@see Any} carrying the reason, rather than failing, so a package using generics (even
     * incorrectly) still loads.
     *
     * @param array<mixed> $raw
     */
    private static function application(array $raw): TypeInterface
    {
        if (count($raw) !== 1) {
            return new Any(false, self::MALFORMED_KEY_COUNT);
        }

        $key = array_key_first($raw);

        if ($key === 'array') {
            $element = $raw[$key];

            if (is_int($element) || is_float($element) || is_bool($element)) {
                return new Any(false, self::MALFORMED_ARGUMENT);
            }

            return self::array(self::parse($element));
        }

        if (!is_string($key) || strncmp($key, 'object.', 7) !== 0 || $key === 'object.') {
            return new Any(false, self::MALFORMED_KEY_PREFIX);
        }

        $value = $raw[$key];

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return new Any(false, self::MALFORMED_ARGUMENT);
        }

        return new Base('object', substr($key, 7), self::parse($value));
    }

    private static function allowedRefinement(string $baseType, ?string $refinement): ?string
    {
        if ($refinement === null) {
            return null;
        }

        return in_array($refinement, self::ALLOWED_REFINEMENTS[$baseType], true) ? $refinement : null;
    }

    public static function string(?string $refinement = null): TypeInterface
    {
        return new Base('string', $refinement);
    }

    public static function number(?string $refinement = null): TypeInterface
    {
        return new Base('number', $refinement);
    }

    public static function object(?string $refinement = null): TypeInterface
    {
        return new Base('object', $refinement);
    }

    public static function array(?TypeInterface $of = null): TypeInterface
    {
        return new ArrayOf($of !== null ? $of : self::any());
    }

    public static function boolean(): TypeInterface
    {
        return new Base('boolean');
    }

    public static function null(): TypeInterface
    {
        return new Base('null');
    }

    /**
     * @param array<int, TypeInterface> $types
     */
    public static function union(array $types): TypeInterface
    {
        $unique = [];

        foreach ($types as $type) {
            $found = false;

            foreach ($unique as $existing) {
                if ($existing->equals($type)) {
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                $unique[] = $type;
            }
        }

        if (count($unique) > 1) {
            return new Union($unique);
        }

        return $unique[0];
    }

    public static function any(): TypeInterface
    {
        return new Any();
    }

    /**
     * Validates a value against a named refinement (e.g. `email`, `uuid`, `u32`). Must stay in sync with
     * {@see self::ALLOWED_REFINEMENTS}.
     *
     * @internal Used by {@see Base::isValid()}.
     *
     * @param mixed $value
     */
    public static function validateRefinement(string $refinement, $value): bool
    {
        switch ($refinement) {
            case 'u32':
                return is_int($value) && $value >= 0 && $value <= 0xFFFFFFFF;
            case 'u64':
                return is_int($value) && $value >= 0;
            case 'i32':
                return is_int($value) && $value >= -0x80000000 && $value <= 0x7FFFFFFF;
            case 'i64':
                return is_int($value);
            case 'f32':
                return is_numeric($value) && is_finite((float) $value) && abs((float) $value) <= 3.4028235e38;
            case 'f64':
                return is_numeric($value) && is_finite((float) $value);
            case 'timestamp':
                return is_int($value) && $value >= 0;
            case 'date':
                return is_string($value) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
            case 'time':
                return is_string($value) && (bool) preg_match('/^\d{2}:\d{2}:\d{2}(\.\d+)?$/', $value);
            case 'datetime':
                return is_string($value) && (bool) preg_match(
                    '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?$/',
                    $value
                );
            case 'uuid':
                return is_string($value) && (bool) preg_match(
                    '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
                    $value
                );
            case 'base64':
                return is_string($value)
                    && strlen($value) % 4 === 0
                    && (bool) preg_match('#^[A-Za-z0-9+/]*={0,2}$#', $value);
            case 'email':
                return is_string($value) && (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $value);
            case 'phone':
                return is_string($value) && (bool) preg_match('/^\+[1-9]\d{1,14}$/', $value);
            case 'url':
                return is_string($value) && self::isValidUrl($value);
            case 'uri':
                return is_string($value) && self::isValidUri($value);
            case 'ipv4':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            case 'ipv6':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            case 'hostname':
                return is_string($value) && (bool) preg_match(
                    '/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/',
                    $value
                );
            default:
                return true;
        }
    }

    private static function isValidUrl(string $value): bool
    {
        $parts = parse_url($value);

        return $parts !== false
            && isset($parts['scheme'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && !empty($parts['host']);
    }

    private static function isValidUri(string $value): bool
    {
        $parts = parse_url($value);

        return $parts !== false && !empty($parts['scheme']);
    }
}