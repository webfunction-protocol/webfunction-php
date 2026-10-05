<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use WebFunction\BadRequestError;
use WebFunction\Client;
use WebFunction\Package;
use WebFunction\Page;
use WebFunction\Pipeline;
use WebFunction\Request;
use WebFunction\Type;

function check(string $label, bool $condition): void
{
    echo ($condition ? "OK   " : "FAIL ") . $label . "\n";

    if (!$condition) {
        exit(1);
    }
}

// ---- Type system -----------------------------------------------------------

$t = Type::parse(['object', 'null']);
check('union format', $t->format() === 'object | null');

$t = Type::parse([['string']]);
check('array-of-string format', $t->format() === 'array<string>');

$t = Type::parse('array');
check('bare array format', $t->format() === 'array<any>');

$t = Type::parse(null);
check('null -> any', $t->format() === 'any');

$t = Type::parse('string.email');
check('email valid', $t->isValid('ada@example.com'));
check('email invalid', !$t->isValid('not-an-email'));

$t = Type::parse('object.user');
check('object refs', $t->objects() === ['user']);

// ---- Package / Endpoint introspection --------------------------------------

$packageData = [
    'name' => 'Example API',
    'base_url' => 'https://api.example.com/',
    'endpoints' => [
        [
            'name' => 'find-user',
            'returns' => 'object',
            'docs' => 'Retrieves user data.',
            'group' => 'Users',
            'arguments' => [
                ['name' => 'id', 'type' => 'string', 'flags' => ['required'], 'docs' => 'Identifier of the user.'],
            ],
            'attributes' => [
                ['name' => 'name', 'type' => 'string'],
            ],
        ],
        ['name' => 'list-items', 'returns' => [['object']]],
        ['name' => 'list-people', 'returns' => 'object', 'flags' => ['paginated']],
    ],
];

$package = Package::fromArray($packageData);
check('package name', $package->getName() === 'Example API');

$endpoint = $package->endpoint('find-user');
check('endpoint lookup by hyphen', $endpoint !== null);

$endpoint2 = $package->endpoint('find_user');
check('endpoint lookup by underscore', $endpoint2 === $endpoint);

$idArg = $endpoint->getArgument('id');
check('argument required', $idArg->isRequired());
check('argument type', (string) $idArg->getType() === 'string');

$nameAttr = $endpoint->getAttribute('name');
check('attribute lookup', $nameAttr !== null && (string) $nameAttr->getType() === 'string');

check('list-items returns array<object>', (string) $package->endpoint('list-items')->getReturns() === 'array<object>');
check('list-people is paginated', $package->endpoint('list-people')->isPaginated());

$privatePackage = Package::fromArray([
    'base_url' => 'https://api.example.com/',
    'endpoints' => [
        ['name' => 'internal-sync', 'returns' => 'boolean', 'flags' => ['private']],
        [
            'name' => 'find-account',
            'returns' => 'object',
            'arguments' => [
                ['name' => 'id', 'type' => 'string', 'flags' => ['required']],
                ['name' => 'debug', 'type' => 'boolean', 'flags' => ['private']],
            ],
            'attributes' => [
                ['name' => 'email', 'type' => 'string'],
                ['name' => 'audit_ref', 'type' => 'string', 'flags' => ['private']],
            ],
        ],
    ],
]);
$accountEndpoint = $privatePackage->endpoint('find-account');
check('endpoint private flag', $privatePackage->endpoint('internal-sync')->isPrivate() && !$accountEndpoint->isPrivate());
check('argument private flag', $accountEndpoint->getArgument('debug')->isPrivate() && !$accountEndpoint->getArgument('id')->isPrivate());
check('attribute private flag', $accountEndpoint->getAttribute('audit_ref')->isPrivate() && !$accountEndpoint->getAttribute('email')->isPrivate());

// ---- Client calls via a stubbed HTTP client --------------------------------

Request::setHttpClient(function (string $url, array $headers, ?string $body) {
    $decoded = $body ? json_decode($body, true) : [];

    if (str_ends_with($url, '/find-user')) {
        if (($decoded['id'] ?? null) === 'missing') {
            return [400, json_encode(['USER_NOT_FOUND', 'No user with that id.', ['id' => 'missing']])];
        }

        return [200, json_encode(['id' => $decoded['id'], 'name' => 'Ada'])];
    }

    if (str_ends_with($url, '/list-people')) {
        $offset = $decoded['offset'] ?? 0;

        if ($offset === 0) {
            return [200, json_encode([
                'page' => [['person_id' => 'p1'], ['person_id' => 'p2']],
                'next' => ['offset' => 2],
                'previous' => null,
            ])];
        }

        return [200, json_encode([
            'page' => [['person_id' => 'p3']],
            'next' => null,
            'previous' => ['offset' => 0],
        ])];
    }

    return [200, json_encode(null)];
});

$client = Client::fromPackage($package);

$result = $client->findUser(['id' => '123']);
check('client call result', $result === ['id' => '123', 'name' => 'Ada']);

$result2 = $client->call('find-user', ['id' => '123']);
check('client call by raw name', $result2 === $result);

try {
    $client->findUser(['id' => 'missing']);
    check('bad request throws', false);
} catch (BadRequestError $e) {
    check('bad request code', $e->getErrorCode() === 'USER_NOT_FOUND');
    check('bad request details', $e->getDetails() === ['id' => 'missing']);
}

try {
    $client->doesNotExist();
    check('missing endpoint throws', false);
} catch (\BadMethodCallException $e) {
    check('missing endpoint throws BadMethodCallException', true);
}

// ---- Pagination -------------------------------------------------------------

$page = $client->listPeople(['offset' => 0]);
check('page is Page', $page instanceof Page);
check('page items', $page->getItems() === [['person_id' => 'p1'], ['person_id' => 'p2']]);
check('page has next', $page->hasNext());
check('page has no previous', !$page->hasPrevious());

$next = $page->nextPage();
check('next page items', $next->getItems() === [['person_id' => 'p3']]);
check('next page has previous', $next->hasPrevious());
check('next page has no next', !$next->hasNext());

// ---- Pipelining ---------------------------------------------------------

Request::setHttpClient(function (string $url, array $headers, ?string $body) {
    $decoded = $body ? json_decode($body, true) : [];

    if (str_ends_with($url, '/run-pipeline')) {
        // Emulate a server resolving two steps, echoing back the (already-resolved) body of step 2.
        return [200, json_encode([
            ['id' => '123', 'name' => 'Ada'],
            ['id' => 'order-1', 'user_id' => '123'],
        ])];
    }

    return [200, json_encode(null)];
});

$pipeline = new Pipeline('https://api.example.com/run-pipeline');
$userPromise = $pipeline->addStep(['url' => 'https://api.example.com/find-user', 'headers' => [], 'body' => ['id' => '123']]);
$orderPromise = $pipeline->addStep([
    'url' => 'https://api.example.com/create-order',
    'headers' => [],
    'body' => ['user_id' => $userPromise['id']],
]);

$resolvedOrder = $orderPromise->resolve();
check('pipeline resolves', $resolvedOrder === ['id' => 'order-1', 'user_id' => '123']);
check('first promise also resolved', $userPromise->value() === ['id' => '123', 'name' => 'Ada']);

echo "\nAll smoke tests passed.\n";