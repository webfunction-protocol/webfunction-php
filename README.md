# webfunction-php

A [Web Function](https://webfunction.org) client for PHP, ported from the
[official Ruby gem](https://github.com/webfunction-protocol/webfunction-ruby).

Web Function is a way to design APIs. There are no verbs and no nested URLs.
You call an endpoint with a POST request, the path names the action, and the
JSON body carries the data. This library lets you call those endpoints from
PHP.

```php
$client = \WebFunction\Client::fromPackageEndpoint("https://api.example.com/package");

$client->findUser(["id" => "123"]);
// => ["id" => "123", "name" => "Ada"]
```
## Table of contents

- [Why Web Function](#why-web-function)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Clients](#clients)
- [Calling endpoints](#calling-endpoints)
- [Pagination](#pagination)
- [Authentication](#authentication)
- [Versioning](#versioning)
- [Inspecting a package](#inspecting-a-package)
- [Types](#types)
- [Object schemas](#object-schemas)
- [Error handling](#error-handling)
- [Pipelining](#pipelining)
- [Custom HTTP client](#custom-http-client)
- [Low-level requests](#low-level-requests)
- [Command line tool](#command-line-tool)
- [Differences from the Ruby gem](#differences-from-the-ruby-gem)
- [Development](#development)
- [License](#license)

## Why Web Function

A Web Function API has a few simple rules:

- Every call is an HTTP POST.
- The request body is a JSON object.
- The response body is any JSON value.
- A `200` status means success and the body is the return value.
- A `400` status means the request was bad and the body explains why.
- Any other status is an error you handle yourself.

On top of that, an API can publish a **package**: a JSON document listing the
endpoints, their arguments, their return types, and their docs. This library
reads a package and gives you a client that calls those endpoints as if they
were PHP methods.

Read the full specification at [webfunction.org](https://webfunction.org).

## Installation

This library has no external runtime dependencies beyond `ext-json` and
`ext-curl`, both bundled with most PHP installs.

Via Composer, once published:

```bash
composer require webfunction-protocol/webfunction
```

Or, without Composer, `require` the bundled autoloader:

```php
require "path/to/webfunction-php/autoload.php";
```

Requires PHP 7.3 or newer. (Polyfills are bundled for `str_starts_with()`,
`str_ends_with()`, `str_contains()`, and `array_is_list()` on PHP versions
that predate them.)

## Quick start

```php
require "vendor/autoload.php";

use WebFunction\Client;

$client = Client::fromPackageEndpoint("https://api.example.com/package");

// An endpoint named "list-items" becomes the method listItems() (or list_items()).
$items = $client->listItems(["limit" => 10]);
// => [["id" => 1], ["id" => 2]]

// Pass a bearer token for endpoints that need authentication.
$secure = Client::fromPackageEndpoint(
    "https://api.example.com/package",
    bearerAuth: "my-token",
);

$secure->createItem(["name" => "Notebook"]);
// => ["id" => 3, "name" => "Notebook"]
```

## Clients

A `WebFunction\Client` wraps a package and turns each endpoint into a method.

Build one from a package URL — the library fetches the package, reads its
endpoints, and returns a ready client:

```php
$client = Client::fromPackageEndpoint("https://api.example.com/package");
```

`fromPackageEndpoint()` fetches the package by calling the URL as a Web
Function endpoint (a POST request). If your package document is served as
plain JSON over a regular GET request instead, use `fromUrl()`:

```php
$client = Client::fromUrl("https://api.example.com/package.json");
```

`fromUrl()` accepts the same options as `fromPackageEndpoint()`. When you pass
a `version`, it is added to the request as an `api_version` query parameter
rather than an `Api-Version` header.

If you already have a package in memory, build the client from that instead —
this avoids the extra request:

```php
use WebFunction\Package;

$package = Package::fromArray([
    "base_url" => "https://api.example.com/",
    "endpoints" => [
        ["name" => "list-items", "returns" => [["object"]]],
        ["name" => "create-item", "returns" => ["object"]],
    ],
]);

$client = Client::fromPackage($package);
```

All three builders accept the same options:

| Option        | Description                                                       |
| ------------- | ------------------------------------------------------------------ |
| `bearerAuth`  | A bearer token sent with every call.                               |
| `version`     | A version string sent in the `Api-Version` header.                 |
| `pipelined`   | When `true`, calls are batched into one request. See [Pipelining](#pipelining). |

You can also change these after the fact with `$client->setBearerAuth(...)`,
`$client->setVersion(...)`, and `$client->setPipeline(...)`.

## Calling endpoints

Endpoint names use dashes, like `list-items`. The client exposes them as PHP
methods in camelCase (`listItems`) — `list_items` also works. Pass arguments
as an associative array:

```php
$client->listItems(["limit" => 10, "offset" => 20]);
```

The return value is the decoded JSON response: an array, string, number,
boolean, or `null`. When the response matches the pagination contract, it is
wrapped in a `WebFunction\Page` instead — see [Pagination](#pagination).

```php
$client->getCount();           // => 42
$client->listItems();          // => [["id" => 1]]
$client->findUser(["id" => "1"]); // => ["id" => "1", "name" => "Ada"]
```

If you prefer to call an endpoint by its raw name, use `call()`:

```php
$client->call("list-items", ["limit" => 10]);
```

Calling an endpoint that the package does not define throws
`\BadMethodCallException`:

```php
$client->doesNotExist();
// => \BadMethodCallException
```

## Pagination

Some endpoints return results in pages. A paginated response is a JSON object
with three keys: `page` (the items), `next`, and `previous`. The library
detects this shape automatically and returns a `WebFunction\Page` instead of a
bare array.

```php
$page = $client->listPeople(["filters" => ["first_name" => "Joe"]]);
// => WebFunction\Page

$page->getItems();
// => [["person_id" => "person_1", "first_name" => "Joe"], ...]

$page->hasNext();     // => true
$page->hasPrevious();  // => false
```

`Page` implements `IteratorAggregate` and `Countable`, so you can `foreach`
over it or `count()` it directly.

To move between pages, call `nextPage()` or `previousPage()`. Each call posts
the opaque `next` or `previous` body from the last response to the same
endpoint — you never build or change those bodies yourself:

```php
$nextPage = $page->nextPage();
// => WebFunction\Page

$nextPage->hasPrevious(); // => true
$nextPage->previousPage(); // => back to the earlier page
```

When there is no adjacent page, `hasNext()` / `hasPrevious()` are `false` and
`nextPage()` / `previousPage()` return `null`.

You can check whether an endpoint declares the `paginated` flag:

```php
$endpoint = $client->getPackage()->endpoint("list-people");
$endpoint->isPaginated(); // => true
```

The full contract is at [webfunction.org/pagination](https://webfunction.org/pagination).

## Authentication

Some endpoints need a bearer token. Pass it when you build the client and the
library adds an `Authorization: Bearer <token>` header to every call:

```php
$client = Client::fromPackageEndpoint(
    "https://api.example.com/package",
    bearerAuth: "my-token",
);

$client->listOrders();
```

The library does not handle login; how you obtain the token is up to you. To
find out whether an endpoint needs one, check its flags:

```php
$endpoint = $client->getPackage()->endpoint("list-orders");
$endpoint->requiresBearerAuth(); // => true
$endpoint->capturesBearer();     // => false
```

## Versioning

A versioned package selects its version through the `Api-Version` header.
Pass a version string when you build the client:

```php
$client = Client::fromPackageEndpoint(
    "https://api.example.com/package",
    version: "2024-01-01",
);
```

You can ask a package whether it is versioned and which versions it offers:

```php
$package = $client->getPackage();
$package->isVersioned(); // => true
$package->getVersion();  // => "2024-01-01"
$package->getVersions(); // => ["2023-06-01", "2024-01-01"]
```

## Inspecting a package

A package describes itself. You can read its metadata, walk its endpoints,
and look at the arguments and outputs of each one.

```php
$package = $client->getPackage();

$package->getName();     // => "Example API"
$package->getBaseUrl();  // => "https://api.example.com/"
$package->getDocs();     // => "Markdown documentation for the package."
$package->getEndpoints(); // => [WebFunction\Endpoint, ...]
```

Look up a single endpoint by name. Underscores and dashes both work:

```php
$endpoint = $package->endpoint("find-user");
// Same as:
$endpoint = $package->endpoint("find_user");

$endpoint->getName();  // => "find-user"
$endpoint->getDocs();  // => "Retrieves user data."
$endpoint->getReturns(); // => a Type, see below
(string) $endpoint->getReturns(); // => "object"
$endpoint->getGroup(); // => "Users"
```

Each endpoint lists the arguments it takes:

```php
$endpoint->getArguments();
// => [WebFunction\Argument, ...]

$id = $endpoint->getArgument("id");
$id->getName();      // => "id"
(string) $id->getType(); // => "string"
$id->isRequired();   // => true
$id->isOptional();   // => false
$id->getChoices();   // => []
$id->getDocs();      // => "Identifier of the user."
```

It also lists the attributes it returns when the return type is an object:

```php
$name = $endpoint->getAttribute("name");
$name->getName();      // => "name"
(string) $name->getType(); // => "string"
$name->isNullable();  // => false
$name->getValues();   // => []
```

You can call an endpoint object directly once it belongs to a client:

```php
$endpoint = $client->getPackage()->endpoint("find-user");
$endpoint->call(["id" => "123"]);
// => ["id" => "123", "name" => "Ada"]
```

## Types

An endpoint's return type, an argument's type, and an attribute's type are all
`WebFunction\Type\TypeInterface` instances rather than plain strings, parsed
from the package once.

Every type supports `format()` and `__toString()`:

```php
$type = $endpoint->getArgument("email")->getType();

(string) $type;             // => "string.email"
$type->format("compact");   // => "email"
$type->format("base");      // => "string"
```

A type also knows how to validate a value against itself, checking both the
base type and any refinement:

```php
$type->isValid("ada@example.com"); // => true
$type->isValid("not-an-email");    // => false
```

The base types are `string`, `number`, `object`, `boolean`, and `null`.
`string` and `number` may carry a refinement:

- `string`: `date`, `time`, `datetime`, `uuid`, `base64`, `email`, `phone`,
  `url`, `uri`, `ipv4`, `ipv6`, `hostname`.
- `number`: `u32`, `u64`, `i32`, `i64`, `f32`, `f64`, `timestamp`.

Types compose. A package can declare an array of a type, a union of several
types, or an open `any` type. A top-level array of type strings is read as a
union, while a nested array denotes an array whose elements have the inner
type:

```php
use WebFunction\Type;

(string) Type::parse(["object", "null"]); // => "object | null"
(string) Type::parse([["string"]]);       // => "array<string>"
(string) Type::parse("array");            // => "array<any>"
(string) Type::parse(null);               // => "any"
```

When a type refers to a named object definition (see below), `objects()`
lists the names it references:

```php
Type::parse("object.user")->objects(); // => ["user"]
```

A generic object (an object definition using the type parameter `T`) is applied
to a type with `{"object.list": "object.user"}`, which reads as
`object.list<object.user>`. The library doesn't resolve generics itself, and
never fails on them: `getArg()` returns the argument, `objects()` includes the
names inside it, an unresolved `T` reads as `any` (`isParam()` is true, for
tooling that wants to tell it from a literal `any`), and a malformed application
becomes `any` with the reason in `getMalformed()`:

```php
(string) Type::parse(["object.list" => "object.user"]); // => "object.list<object.user>"
(string) Type::parse([["T"]]);                          // => "array<any>"
```

(An empty JSON object decodes to the same empty array as `[]`, so it can't be
told apart from an empty declaration; it resolves to `any` either way.)

## Object schemas

A package can declare named object definitions under its `objects` key. Any
type can then refer to one as `object.<name>`, letting several endpoints share
the same object shape instead of repeating its fields.

```php
$package->getObjects();
// => [WebFunction\ObjectSchema, ...]
```

An object may be referenced in two contexts, and each uses a different member
set — pass the context to `object()`:

```php
use WebFunction\ObjectSchema;

$user = $package->object("user", ObjectSchema::CONTEXT_ATTRIBUTES);

$user->getName();       // => "user"
$user->getAttributes(); // => [WebFunction\Attribute, ...]
$user->getAttribute("email")->getType(); // => string.email
```

If the object is not defined, or defines no members for the requested
context, `object()` returns `null`.

## Error handling

Every exception this library throws extends `WebFunction\Error`. Each carries
an `errorCode` and optional `details`.

```php
try {
    $client->findUser(["id" => "missing"]);
} catch (\WebFunction\Error $e) {
    $e->getErrorCode(); // => "USER_NOT_FOUND"
    $e->getMessage();   // => "No user with that id."
    $e->getDetails();   // => ["id" => "missing"]
}
```

> `getErrorCode()` (not `getCode()`) carries the Web Function error code,
> because `Exception::getCode()` is `final` on PHP's base exception classes
> and returns an `int`.

These are the exception classes:

| Class                                  | Thrown when                                                 |
| --------------------------------------- | ------------------------------------------------------------ |
| `WebFunction\BadRequestError`           | The server replied with status `400`.                        |
| `WebFunction\UnexpectedStatusCodeError` | The server replied with a status other than `200` or `400`.   |
| `WebFunction\JsonParseError`            | The response body was not valid JSON.                        |
| `WebFunction\UnresolvedPromiseError`    | A pipeline promise was read before it resolved.               |

When the server returns a `400`, the body is an error triple: a JSON array
with three parts — a code, a message, and details:

```json
["USER_NOT_FOUND", "No user with that id.", { "id": "missing" }]
```

If the body is not a triple, `BadRequestError` is still thrown, with code
`WFN_BAD_REQUEST_ERROR` and the raw body in `details`.

An endpoint can document the errors it may return — relevant when it declares
the `error_triple` flag:

```php
$endpoint->getErrors();
// => [WebFunction\DocumentedError, ...]

$error = $endpoint->getError("USER_NOT_FOUND");
$error->getCode(); // => "USER_NOT_FOUND"
$error->getDocs(); // => "Returned when no user matches the id."
```

The package can document shared errors too: `$package->getErrors()`,
`$package->error("RATE_LIMITED")`.

## Pipelining

Pipelining sends several calls in one HTTP request. The server runs them in
order, and you can feed the output of one call into the next.

Build a pipelined client and each call returns a `WebFunction\Promise` instead
of a value:

```php
$client = Client::fromPackageEndpoint(
    "https://api.example.com/package",
    pipelined: true,
);

$user  = $client->findUser(["id" => "123"]);       // => a Promise
$order = $client->createOrder(["user_id" => $user["id"]]); // uses the first result

$order->resolve();
// => ["id" => "order-1", "user_id" => "123"]
```

Indexing `$user["id"]` before the call runs doesn't return a value — it
returns a `WebFunction\Promise\Path`, a reference into the future result. The
library sends that path to the server, which fills it in when it runs the
second call. Calling `resolve()` runs the whole pipeline and returns the
value.

Once a pipeline runs, every promise from that batch holds its value:

```php
$user->resolve();  // runs the pipeline
$order->value();   // already available, no extra request
```

You can also drive a pipeline by hand with `WebFunction\Pipeline`:

```php
use WebFunction\Pipeline;

$pipeline = new Pipeline("https://api.example.com/run-pipeline");

$pipeline->addStep(["url" => "https://api.example.com/a", "headers" => [], "body" => []]);
$pipeline->addStep(["url" => "https://api.example.com/b", "headers" => [], "body" => []]);

$pipeline->execute("all"); // => [["a" => 1], ["b" => 2]]
```

The `returns` argument controls what comes back:

- `"all"` returns every step result as an array (the default).
- `"last"` returns only the last step's result.
- Any other string is treated as a JSONPath expression, e.g. `"$[0].id"`.

## Custom HTTP client

By default this library makes requests with a small cURL-based client. Swap in
any HTTP client by calling `Request::setHttpClient()` with a callable.

The callable receives the URL, the headers, and the raw JSON body (`null` for
a GET). It must return a two-element array of `[statusCode, rawResponseBody]`:

```php
use WebFunction\Request;

Request::setHttpClient(function (string $url, array $headers, ?string $body): array {
    $response = MyHttp::post($url, headers: $headers, body: $body);

    return [$response->status, $response->body];
});
```

This is also handy in tests, where you can return a canned response without
making a real request:

```php
Request::setHttpClient(function ($url, $headers, $body) {
    return [200, json_encode(["id" => "123"])];
});
```

Call `Request::setHttpClient(null)` to restore the default.

## Low-level requests

If you don't need a package, call a single endpoint URL directly with
`WebFunction\Request`:

```php
use WebFunction\Request;

Request::execute(
    "https://api.example.com/find-user",
    bearerAuth: "my-token",
    version: "2024-01-01",
    args: ["id" => "123"],
);
// => ["id" => "123", "name" => "Ada"]
```

This adds the standard headers, posts the JSON body, and parses the response,
throwing the same exceptions described in [Error handling](#error-handling).

## Command line tool

A `wfn` script (in `bin/`) mirrors the Ruby gem's CLI. Call an endpoint from
the shell, with arguments as a JSON string:

```bash
bin/wfn call https://api.example.com/find-user '{"id":"123"}'
```

Pass a bearer token with `--auth` and a version with `--version`:

```bash
bin/wfn call --auth my-token --version 2024-01-01 \
  https://api.example.com/list-orders '{}'
```

The command prints the response as formatted JSON. On error it prints the
code, the message, and the details, then exits with a non-zero status.

## Differences from the Ruby gem

This is a faithful port, not a 1:1 transliteration — a few things follow PHP
idiom instead of Ruby's, and the whole library deliberately targets PHP 7.3+
rather than the newest syntax:

- Accessors are explicit getters (`getName()`, `isRequired()`) rather than
  Ruby's bare attribute readers, since PHP has no `attr_reader`.
- `Client` uses PHP's `__call()` magic method instead of Ruby's
  `method_missing` on `BasicObject`; both `camelCase` and `snake_case` method
  names resolve to the hyphenated endpoint name.
- Endpoint/argument/attribute exceptions expose `getErrorCode()` rather than
  `getCode()`, since `Exception::getCode()` is `final` and typed `int` on
  PHP's base exception classes.
- Indexing a `Promise` or `Promise\Path` uses PHP's `ArrayAccess`
  (`$promise["id"]`) to mirror Ruby's `promise["id"]`.
- `Page` implements `IteratorAggregate` and `Countable` instead of Ruby's
  `Enumerable`.
- No constructor property promotion, `readonly` properties, typed properties,
  `match`, nullsafe `?->`, the `mixed` type, named arguments, or
  `array_is_list()`/`str_starts_with()`/`str_ends_with()`/`str_contains()` —
  all either 7.4+ or 8.x-only. Properties carry `@var` docblocks instead of
  native types, and the four string/array functions are polyfilled in
  `src/polyfills.php` when the running PHP predates them.
- The code has been reviewed line-by-line against the PHP 7.3 language
  reference and linted under `php -l`, but has not been run on an actual 7.3
  interpreter (only 8.3 was available in the environment this was built in)
  — worth a smoke-test pass on your real target runtime before you ship it.

## Development

Run the smoke test (no PHPUnit required):

```bash
php tests/smoke.php
php tests/generics.php
```

If you have Composer and PHPUnit available, `composer test` runs the same
kind of checks under a proper test runner once you add test cases under
`tests/`.

## License

MIT — see [LICENSE.txt](LICENSE.txt). This is an independent, unofficial port;
it is not published or endorsed by the Web Function project or the author of
the original Ruby gem.