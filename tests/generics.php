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

// ---- Array applications ---------------------------------------------------------
// {"array": X} is the canonical way to write an array of X; it must read like the legacy [[X]].

$pairs = [
    ['{ "array": "string" }', '[["string"]]'],
    ['{ "array": "object.user" }', '[["object.user"]]'],
    ['{ "array": ["string", "null"] }', '[["string", "null"]]'],
    ['{ "array": "T" }', '[["T"]]'],
    ['{ "array": { "array": "string" } }', '[[["string"]]]'],
    ['{ "array": { "object.page": "object.user" } }', '[[{ "object.page": "object.user" }]]'],
    ['{ "object.page": { "array": "object.user" } }', '{ "object.page": [["object.user"]] }'],
    ['[{ "array": "string" }, "null"]', '[["string"], "null"]'],
];

foreach ($pairs as $pair) {
    $a = parseJson($pair[0]);
    $l = parseJson($pair[1]);
    check(
        "array application {$pair[0]} reads like {$pair[1]}",
        $a->equals($l)
            && $a->format() === $l->format()
            && $a->format('base') === $l->format('base')
            && $a->format('compact') === $l->format('compact')
            && $a->objects() === $l->objects()
    );
}

check('array application of string', fmt('{ "array": "string" }') === 'array<string>');
check('array application of an object', fmt('{ "array": "object.user" }') === 'array<object.user>');
check('array application of a union', fmt('{ "array": ["string", "number"] }') === 'array<string | number>');
check('array application of an array application', fmt('{ "array": { "array": "string" } }') === 'array<array<string>>');
check('array application of a generic application', fmt('{ "array": { "object.page": "object.user" } }') === 'array<object.page<object.user>>');
check('generic application of an array application', fmt('{ "object.page": { "array": "object.user" } }') === 'object.page<array<object.user>>');
check('array application of T', fmt('{ "array": "T" }') === 'array<any>');
check('array application in a union', fmt('[{ "array": "string" }, "null"]') === 'array<string> | null');
check('bare array beside an array application', fmt('["array", { "array": "string" }]') === 'array<any> | array<string>');

$t = parseJson('{ "array": "object.user" }');
check('array application is an ArrayOf', $t instanceof \WebFunction\Type\ArrayOf && $t->getBaseType() === 'array');
check('array application element', $t->getOf()->format() === 'object.user');
check('array application base format', $t->format('base') === 'array');
$param = parseJson('{ "array": "T" }');
check('T keeps its mark as the element of an array application', $param->getOf() instanceof Any && $param->getOf()->isParam());

check('array application objects()', parseJson('{ "array": "object.user" }')->objects() === ['user']);
check('array application of an application objects()', parseJson('{ "array": { "object.page": "object.user" } }')->objects() === ['page', 'user']);
check('application of an array application objects()', parseJson('{ "object.page": { "array": "object.user" } }')->objects() === ['page', 'user']);
check('nested array application objects()', parseJson('{ "array": { "array": "object.user" } }')->objects() === ['user']);

$strings = parseJson('{ "array": "string" }');
check('array application accepts matching items', $strings->isValid(['a', 'b']) && $strings->isValid([]));
check('array application rejects other items and non-arrays', !$strings->isValid([1]) && !$strings->isValid('a') && !$strings->isValid(null));
$mixed = parseJson('{ "array": ["string", "number"] }');
check('array application element union applies to every item', $mixed->isValid(['a', 1]) && !$mixed->isValid([true]));
$nested = parseJson('{ "array": { "array": "number" } }');
check('nested array applications validate each level', $nested->isValid([[1], []]) && !$nested->isValid([['a']]));

$malformed = [
    ['{ "array": "string", "object.a": "string" }', Type::MALFORMED_KEY_COUNT],
    ['{ "array": 5 }', Type::MALFORMED_ARGUMENT],
    ['{ "array": true }', Type::MALFORMED_ARGUMENT],
    ['{ "arrays": "string" }', Type::MALFORMED_KEY_PREFIX],
    ['{ "array.x": "string" }', Type::MALFORMED_KEY_PREFIX],
    ['{ "Array": "string" }', Type::MALFORMED_KEY_PREFIX],
];

foreach ($malformed as $case) {
    $m = parseJson($case[0]);
    check(
        "malformed {$case[0]} is any with reason {$case[1]}",
        $m instanceof Any && $m->getMalformed() === $case[1] && $m->isValid('anything')
    );
}

// ---- A union of one entry is that entry -------------------------------------------
// The parser already collapses it; these pin it, and the legacy array shorthand.

check('["string"] is string', parseJson('["string"]') instanceof \WebFunction\Type\Base && parseJson('["string"]')->getBaseType() === 'string');
check('["string.email"] compact is email', parseJson('["string.email"]')->format('compact') === 'email');
check('["T"] is T', parseJson('["T"]') instanceof Any && parseJson('["T"]')->isParam());
check('one-entry union of an array application is that array', parseJson('[{ "array": "string" }]') instanceof \WebFunction\Type\ArrayOf);
check('one-entry union of an application is that application', parseJson('[{ "object.list": "object.user" }]')->getRefinement() === 'list');
check('[["string"]] is still an array, not a one-entry union', parseJson('[["string"]]') instanceof \WebFunction\Type\ArrayOf);
check('two entries stay a union', parseJson('["string", "null"]') instanceof \WebFunction\Type\Union);

// ---- A package that uses array applications loads -----------------------------------

$arrayPackage = Package::fromArray(json_decode(<<<'JSON'
{
  "base_url": "https://api.example.com/",
  "endpoints": [
    { "name": "get-comments", "returns": { "array": "object.comment" }, "arguments": [] }
  ],
  "objects": [
    { "name": "comment", "attributes": [
      { "name": "id", "type": "string.uuid" },
      { "name": "message", "type": "string" },
      { "name": "replies", "type": { "array": "object.comment" } }
    ] }
  ]
}
JSON, true));

check('package: array application return', (string) $arrayPackage->endpoint('get-comments')->getReturns() === 'array<object.comment>');
check('package: array application attribute', (string) $arrayPackage->object('comment', 'attributes')->getAttribute('replies')->getType() === 'array<object.comment>');

echo "\nAll generics tests passed.\n";