<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A client wraps a Web Function {@see Package} and provides a convenient interface for invoking its
 * endpoints as ordinary PHP method calls.
 *
 * @example
 *   $client = Client::fromPackageEndpoint("https://api.webfunction.com/package");
 *   $client->listItems(["a" => "b"]); // => ["c" => "d"]
 *
 *   // Or call by the endpoint's raw (hyphenated) name:
 *   $client->call("list-items", ["a" => "b"]);
 */
final class Client
{
    /** @var array<string, string> Maps a PHP-friendly method name (underscored, camelCase-insensitive) to the endpoint's raw name. */
    private $endpoints;

    /** @var string */
    private $baseUrl;

    /** @var Package|null */
    private $package;

    /** @var string|null */
    private $bearerAuth;

    /** @var string|null */
    private $version;

    /** @var Pipeline|null */
    private $pipeline;

    /**
     * @param array<int, string> $endpointNames Raw (hyphenated) endpoint names
     */
    public function __construct(
        string $baseUrl,
        array $endpointNames = [],
        ?Package $package = null,
        ?string $bearerAuth = null,
        ?string $version = null,
        ?Pipeline $pipeline = null
    ) {
        $this->baseUrl = $baseUrl;
        $this->package = $package;
        $this->bearerAuth = $bearerAuth;
        $this->version = $version;
        $this->pipeline = $pipeline;

        $this->endpoints = [];

        foreach ($endpointNames as $endpointName) {
            $this->endpoints[self::methodKey($endpointName)] = $endpointName;
        }
    }

    /**
     * Creates a new client by fetching a package from a Web Function endpoint (i.e. via POST).
     */
    public static function fromPackageEndpoint(
        string $url,
        ?string $bearerAuth = null,
        ?string $version = null,
        bool $pipelined = false
    ): self {
        $response = Request::execute($url, $bearerAuth, $version);
        $package = Package::fromArray(is_array($response) ? $response : []);

        return self::fromPackage($package, $bearerAuth, $version, $pipelined);
    }

    /**
     * Creates a new client by fetching a package from a plain URL (i.e. via GET), rather than as a Web
     * Function endpoint. When a version is given, it is added as an `api_version` query parameter instead
     * of an `Api-Version` header.
     */
    public static function fromUrl(
        string $url,
        ?string $bearerAuth = null,
        ?string $version = null,
        bool $pipelined = false
    ): self {
        $body = Utils::getBodyFromUrl($url, ['api_version' => $version]);
        $package = Package::fromArray(json_decode($body, true, 512, JSON_THROW_ON_ERROR));

        return self::fromPackage($package, $bearerAuth, $version, $pipelined);
    }

    /**
     * Creates a new client from an already-built {@see Package}. Avoids an extra request when you already
     * have the package in memory.
     */
    public static function fromPackage(
        Package $package,
        ?string $bearerAuth = null,
        ?string $version = null,
        bool $pipelined = false
    ): self {
        $pipeline = $pipelined ? $package->pipeline() : null;

        $endpointNames = array_map(function (Endpoint $e) {
            return $e->getName();
        }, $package->getEndpoints());

        $client = new self($package->getBaseUrl(), $endpointNames, $package, $bearerAuth, $version, $pipeline);

        foreach ($package->getEndpoints() as $endpoint) {
            $endpoint->setClient($client);
        }

        return $client;
    }

    /**
     * Calls an endpoint by its raw name with the given arguments.
     *
     * @param array<string, mixed> $args
     * @return mixed
     */
    public function call(string $endpointName, array $args = [])
    {
        $url = self::joinUrl($this->baseUrl, $endpointName);
        $request = new Request($url, $this->bearerAuth, $this->version, $args);

        if ($this->pipeline !== null) {
            return $this->pipeline->addStep($request->asPipelineStep());
        }

        return $request->run();
    }

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    public function setBearerAuth(?string $bearerAuth): void
    {
        $this->bearerAuth = $bearerAuth;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function setPipeline(?Pipeline $pipeline): void
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Calls an endpoint through its PHP-friendly method name, e.g. `$client->listItems(...)` or
     * `$client->list_items(...)` both call the `list-items` endpoint.
     *
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $key = self::methodKey($name);
        $endpointName = isset($this->endpoints[$key]) ? $this->endpoints[$key] : null;

        if ($endpointName === null) {
            throw new \BadMethodCallException("Undefined endpoint: {$name}");
        }

        /** @var array<string, mixed> $args */
        $args = isset($arguments[0]) ? $arguments[0] : [];

        return $this->call($endpointName, $args);
    }

    /**
     * Normalizes both `snake_case` and `camelCase` PHP method names, and hyphenated endpoint names, to a
     * common lookup key.
     */
    private static function methodKey(string $name): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return str_replace(['-', '_'], '', $snake);
    }

    private static function joinUrl(string $baseUrl, string $endpointName): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($endpointName, '/');
    }
}
