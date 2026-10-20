<?php

declare(strict_types=1);

// Generic object applications and the type parameter `T` in the type grammar.
// Run with: php tests/generics.php

require __DIR__ . '/../autoload.php';

use WebFunction\Package;
use WebFunction\Type;
use WebFunction\Type\Any;

function check(string $label, bool $condition): void
{
    echo ($condition ? "OK   " : "FAIL ") . $label . "\n";

    if (!$condition) {
        exit(1);
    }
}

/** Parses a type from JSON text, the way a real package arrives (objects become associative arrays). */
function parseJson(string $json): \WebFunction\Type\TypeInterface
{
    return Type::parse(json_decode($json, true));
}

function fmt(string $json): string
{
    return parseJson($json)->format();
}

// ---- Existing forms are unchanged -------------------------------------------

check('string', fmt('"string"') === 'string');
check('string.email', fmt('"string.email"') === 'string.email');
check('object.user', fmt('"object.user"') === 'object.user');
check('union', fmt('["string","null"]') === 'string | null');
check('array of string', fmt('[["string"]]') === 'array<string>');
check('array of union', fmt('[["object.person","null"]]') === 'array<object.person | null>');
check('bare array in a union', fmt('["array",["object.person"]]') === 'array<any> | array<object.person>');
check('null is any', Type::parse(null)->format() === 'any');

// ---- Applications ------------------------------------------------------------

$t = parseJson('{ "object.list": "object.user" }');
check('single application', $t->format() === 'object.list<object.user>');
check('application base type', $t->getBaseType() === 'object');
check('application refinement', $t->getRefinement() === 'list');
check('application argument', $t->getArg() !== null && $t->getArg()->format() === 'object.user');
check('application compact format', $t->format('compact') === 'list');
check('application base format', $t->format('base') === 'object');
check('application validates objects', $t->isValid([]) && !$t->isValid('nope'));

check('union argument', fmt('{ "object.list": ["object.user", "null"] }') === 'object.list<object.user | null>');
check('nested application', fmt('{ "object.page": { "object.page": "object.user" } }') === 'object.page<object.page<object.user>>');
check('array argument', fmt('{ "object.list": [["string"]] }') === 'object.list<array<string>>');
check('nested objects()', parseJson('{ "object.page": { "object.page": "object.user" } }')->objects() === ['page', 'user']);

check('application in a union', fmt('[{ "object.list": "object.user" }, "null"]') === 'object.list<object.user> | null');
check('application in an array', fmt('[[{ "object.list": "object.user" }]]') === 'array<object.list<object.user>>');
check('application in an array union', fmt('[[{ "object.list": "object.user" }, "null"]]') === 'array<object.list<object.user> | null>');
check('application with an application in a union argument', fmt('{ "object.list": [{ "object.pair": "number" }, "null"] }') === 'object.list<object.pair<number> | null>');

check('equal applications are equal', parseJson('{ "object.list": "object.user" }')->equals(parseJson('{ "object.list": "object.user" }')));
check('different arguments are not equal', !parseJson('{ "object.list": "object.user" }')->equals(parseJson('{ "object.list": "string" }')));
check('union keeps distinct applications', fmt('[{ "object.list": "string" }, { "object.list": "number" }]') === 'object.list<string> | object.list<number>');
check('union dedupes identical applications', fmt('[{ "object.list": "string" }, { "object.list": "string" }]') === 'object.list<string>');

// ---- Malformed applications resolve to any ------------------------------------

$malformed = [
    ['{ "object.a": "string", "object.b": "string" }', Type::MALFORMED_KEY_COUNT],
    ['{ "string": "object.user" }', Type::MALFORMED_KEY_PREFIX],
    ['{ "list": "object.user" }', Type::MALFORMED_KEY_PREFIX],
    ['{ "object.": "string" }', Type::MALFORMED_KEY_PREFIX],
    ['{ "object.list": 5 }', Type::MALFORMED_ARGUMENT],
    ['{ "object.list": true }', Type::MALFORMED_ARGUMENT],
];

foreach ($malformed as [$json, $reason]) {
    $t = parseJson($json);
    check("malformed $json is any", $t->format() === 'any');
    check("malformed $json reason", $t instanceof Any && $t->getMalformed() === $reason);
    check("malformed $json accepts anything", $t->isValid('anything') && $t->isValid(['x' => 1]));
}

$t = parseJson('[{ "string": "x" }, "null"]');
check('malformed entry in a union is kept as any', $t->format() === 'any | null');

// An empty JSON object decodes to the same empty array as `[]`, so it cannot be told apart
// from an empty declaration; it resolves to any either way.
check('empty object resolves to any', fmt('{}') === 'any');

check('well formed types are not malformed', !(Type::any()->getMalformed() !== null) && !Type::any()->isParam());

// ---- The type parameter T -----------------------------------------------------

check('T is any', fmt('"T"') === 'any');
check('T in an array is any', fmt('[["T"]]') === 'array<any>');
check('T in a union is any', fmt('["T","null"]') === 'any | null');
check('T accepts anything', parseJson('"T"')->isValid('anything') && parseJson('"T"')->isValid(['x' => 1]));

$t = parseJson('"T"');
check('T is marked as a parameter', $t instanceof Any && $t->isParam() && $t->getBaseType() === 'any');
$arr = parseJson('[["T"]]');
check('T keeps its mark inside an array', $arr instanceof \WebFunction\Type\ArrayOf && $arr->getOf() instanceof Any && $arr->getOf()->isParam());
$union = parseJson('["T","null"]');
check('T keeps its mark inside a union', $union instanceof \WebFunction\Type\Union && $union->getMembers()[0]->isParam());

check('a literal any is not a parameter', !parseJson('"any"')->isParam());
check('object.T is an ordinary object reference', fmt('"object.T"') === 'object.T' && !(parseJson('"object.T"') instanceof Any));

// ---- A package that uses generics loads ----------------------------------------

$package = Package::fromArray(json_decode(<<<'JSON'
{
  "base_url": "https://api.example.com/",
  "endpoints": [
    {
      "name": "list-users",
      "returns": { "object.list": "object.user" },
      "arguments": [{ "name": "filter", "type": [{ "object.pair": "string" }, "null"] }]
    },
    { "name": "broken", "returns": { "string": "x" }, "arguments": [] }
  ],
  "objects": [
    { "name": "list", "attributes": [
      { "name": "total", "type": "number" },
      { "name": "items", "type": [["T"]] }
    ] }
  ]
}
JSON, true));

$endpoint = $package->endpoint('list-users');
check('package: endpoint with a generic return', $endpoint !== null && (string) $endpoint->getReturns() === 'object.list<object.user>');
check('package: generic argument type', (string) $endpoint->getArgument('filter')->getType() === 'object.pair<string> | null');
check('package: malformed return is any', (string) $package->endpoint('broken')->getReturns() === 'any');
$list = $package->object('list', 'attributes');
check('package: T attribute is any', $list !== null && (string) $list->getAttribute('items')->getType() === 'array<any>');

echo "\nAll generics tests passed.\n";