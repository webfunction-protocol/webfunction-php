<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A package organizes, documents, and validates {@see Endpoint}s. It bundles a base URL together with the
 * endpoints it exposes, plus optional metadata: name, version information, top-level documentation, common
 * errors, and shared object definitions.
 *
 * See https://webfunction.org/package for the full description of every recognized key and its constraints.
 */
final class Package
{
    use Flaggable;

    /** @var string */
    private $baseUrl;

    /** @var string|null */
    private $pipelineUrl;

    /** @var string|null */
    private $name;

    /** @var string|null */
    private $version;

    /** @var string */
    private $docs;

    /** @var array<int, string> */
    private $versions;

    /** @var array<string, Endpoint> */
    private $endpoints;

    /** @var array<string, DocumentedError> */
    private $errors;

    /** @var array<string, ObjectSchema> */
    private $objects;

    /**
     * @param array<int, string> $flags
     * @param array<int, string> $versions
     * @param array<int, Endpoint> $endpoints
     * @param array<int, DocumentedError> $errors
     * @param array<int, ObjectSchema> $objects
     */
    public function __construct(
        string $baseUrl,
        ?string $pipelineUrl = null,
        ?string $name = null,
        ?string $version = null,
        string $docs = '',
        array $flags = [],
        array $versions = [],
        array $endpoints = [],
        array $errors = [],
        array $objects = []
    ) {
        $this->baseUrl = $baseUrl;
        $this->pipelineUrl = $pipelineUrl;
        $this->name = $name;
        $this->version = $version;
        $this->docs = $docs;
        $this->flags = $flags;
        $this->versions = $versions;

        $this->endpoints = [];

        foreach ($endpoints as $endpoint) {
            $this->endpoints[$endpoint->getName()] = $endpoint;
        }

        $this->errors = [];

        foreach ($errors as $error) {
            $this->errors[$error->getCode()] = $error;
        }

        $this->objects = [];

        foreach ($objects as $object) {
            $this->objects[$object->getName()] = $object;
        }
    }

    /**
     * @param array<string, mixed> $package
     */
    public static function fromArray(array $package): self
    {
        return new self(
            (string) (isset($package['base_url']) ? $package['base_url'] : ''),
            isset($package['pipeline_url']) ? (string) $package['pipeline_url'] : null,
            isset($package['name']) ? (string) $package['name'] : null,
            isset($package['version']) ? (string) $package['version'] : null,
            (string) (isset($package['docs']) ? $package['docs'] : ''),
            Utils::normalizeArrayOfStrings(isset($package['flags']) ? $package['flags'] : []),
            Utils::normalizeArrayOfStrings(isset($package['versions']) ? $package['versions'] : []),
            Endpoint::fromList(isset($package['endpoints']) ? $package['endpoints'] : []),
            DocumentedError::fromList(isset($package['errors']) ? $package['errors'] : []),
            ObjectSchema::fromList(isset($package['objects']) ? $package['objects'] : [])
        );
    }

    /**
     * The base URL for the package. Endpoint URLs are formed by joining this base URL with each endpoint's
     * name. Required for the package to be valid, and MUST use the HTTP or HTTPS scheme.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * A function-pipelining URL used to batch several endpoint invocations into a single request. See the
     * pipelining specification for more information.
     */
    public function getPipelineUrl(): ?string
    {
        return $this->pipelineUrl;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * The version that this package describes. An opaque string. MUST be present when the `versioned` flag
     * is set. See the versioning specification for more information.
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Top-level documentation for the package, formatted as markdown.
     */
    public function getDocs(): string
    {
        return $this->docs;
    }

    /**
     * The versions that are available. MUST be present when the `versioned` flag is set.
     *
     * @return array<int, string>
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    /**
     * The {@see Pipeline} for this package, built from {@see self::getPipelineUrl()}, or `null` when the
     * package does not declare a pipeline URL.
     */
    public function pipeline(): ?Pipeline
    {
        if ($this->pipelineUrl === null) {
            return null;
        }

        return new Pipeline($this->pipelineUrl);
    }

    /**
     * @return array<int, Endpoint>
     */
    public function getEndpoints(): array
    {
        return array_values($this->endpoints);
    }

    /**
     * Looks up a single endpoint by name. Underscores are converted to hyphens so that PHP-style names
     * (e.g. `find_user_by`) match the hyphenated endpoint names used in packages (e.g. `find-user-by`).
     */
    public function endpoint(string $name): ?Endpoint
    {
        $key = str_replace('_', '-', $name);

        return isset($this->endpoints[$key]) ? $this->endpoints[$key] : null;
    }

    /**
     * The list of common errors that any endpoint in this package may return. Only relevant if an endpoint
     * uses the `error_triple` flag. See the error specification for more information.
     *
     * @return array<int, DocumentedError>
     */
    public function getErrors(): array
    {
        return array_values($this->errors);
    }

    public function error(string $code): ?DocumentedError
    {
        return isset($this->errors[$code]) ? $this->errors[$code] : null;
    }

    /**
     * The named object definitions declared by this package. Each can be referenced as a refined object
     * type (`object.<name>`) anywhere a type is expected.
     *
     * @return array<int, ObjectSchema>
     */
    public function getObjects(): array
    {
        return array_values($this->objects);
    }

    /**
     * Looks up a single object definition by name, resolved for the given context. An object that defines
     * no members for the requested context is treated as absent.
     *
     * @param ObjectSchema::CONTEXT_* $context
     */
    public function object(string $name, string $context): ?ObjectSchema
    {
        $object = isset($this->objects[$name]) ? $this->objects[$name] : null;

        if ($object === null) {
            return null;
        }

        if (empty($object->properties($context))) {
            return null;
        }

        return $object;
    }

    /**
     * Whether the package is versioned, i.e. declares the `versioned` flag. A versioned package is selected
     * using the `Api-Version` header.
     */
    public function isVersioned(): bool
    {
        return $this->hasFlag('versioned');
    }
}
