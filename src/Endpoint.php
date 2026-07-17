<?php

declare(strict_types=1);

namespace WebFunction;

use WebFunction\Type\TypeInterface;

/**
 * An endpoint as described in a Web Function package: an operation invocable via the API, along with its
 * name, documentation, arguments (inputs), return type / attributes (outputs), and documented errors.
 *
 * See https://webfunction.org/package and https://webfunction.org/endpoint for more details.
 */
final class Endpoint
{
    use Flaggable;

    /** @var Client|null */
    private $client;

    /** @var string */
    private $name;

    /** @var TypeInterface */
    private $returns;

    /** @var string|null */
    private $group;

    /** @var string */
    private $docs;

    /** @var array<string, Argument> */
    private $arguments;

    /** @var array<string, Attribute> */
    private $attributes;

    /** @var array<string, DocumentedError> */
    private $errors;

    /**
     * @param mixed $returns
     * @param array<int, string> $flags
     * @param array<int, Argument> $arguments
     * @param array<int, Attribute> $attributes
     * @param array<int, DocumentedError> $errors
     */
    public function __construct(
        string $name,
        $returns,
        array $flags = [],
        ?string $group = null,
        string $docs = '',
        array $arguments = [],
        array $attributes = [],
        array $errors = []
    ) {
        $this->client = null;
        $this->name = $name;
        $this->group = $group;
        $this->docs = $docs;
        $this->flags = $flags;
        $this->returns = Type::parse($returns);

        $this->arguments = [];

        foreach ($arguments as $argument) {
            $this->arguments[$argument->getName()] = $argument;
        }

        $this->attributes = [];

        foreach ($attributes as $attribute) {
            $this->attributes[$attribute->getName()] = $attribute;
        }

        $this->errors = [];

        foreach ($errors as $error) {
            $this->errors[$error->getCode()] = $error;
        }
    }

    /**
     * Invokes an endpoint directly over HTTP, without a {@see Package} or {@see Client}.
     *
     * @param array<string, mixed> $args
     * @return mixed
     */
    public static function invoke(string $url, ?string $bearerAuth = null, ?string $version = null, array $args = [])
    {
        return Request::execute($url, $bearerAuth, $version, $args);
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    public static function fromArray(array $endpoint): ?self
    {
        if (!isset($endpoint['name'], $endpoint['returns'])) {
            return null;
        }

        return new self(
            (string) $endpoint['name'],
            $endpoint['returns'],
            Utils::normalizeArrayOfStrings(isset($endpoint['flags']) ? $endpoint['flags'] : []),
            isset($endpoint['group']) ? (string) $endpoint['group'] : null,
            (string) (isset($endpoint['docs']) ? $endpoint['docs'] : ''),
            Argument::fromList(isset($endpoint['arguments']) ? $endpoint['arguments'] : []),
            Attribute::fromList(isset($endpoint['attributes']) ? $endpoint['attributes'] : []),
            DocumentedError::fromList(isset($endpoint['errors']) ? $endpoint['errors'] : [])
        );
    }

    /**
     * @param array<int, mixed> $endpoints
     *
     * @return array<int, self>
     */
    public static function fromList(array $endpoints): array
    {
        return Utils::normalizeArray($endpoints, function ($endpoint) {
            return is_array($endpoint) ? self::fromArray($endpoint) : null;
        });
    }

    /**
     * The {@see Client} used to invoke this endpoint. Assigned when the endpoint is loaded from a package.
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * The suffix appended to the package's base URL to form the endpoint's full URL.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The parsed return type of the endpoint.
     */
    public function getReturns(): TypeInterface
    {
        return $this->returns;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getDocs(): string
    {
        return $this->docs;
    }

    /**
     * Invokes the endpoint through its assigned {@see Client}.
     *
     * @param array<string, mixed> $args
     * @return mixed
     *
     * @throws \RuntimeException If no client has been assigned to the endpoint.
     */
    public function call(array $args = [])
    {
        if ($this->client === null) {
            throw new \RuntimeException('Client must be set to invoke an endpoint');
        }

        return $this->client->call($this->name, $args);
    }

    /**
     * @return array<int, DocumentedError>
     */
    public function getErrors(): array
    {
        return array_values($this->errors);
    }

    public function getError(string $code): ?DocumentedError
    {
        return isset($this->errors[$code]) ? $this->errors[$code] : null;
    }

    /**
     * @return array<int, Attribute>
     */
    public function getAttributes(): array
    {
        return array_values($this->attributes);
    }

    public function getAttribute(string $name): ?Attribute
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    /**
     * @return array<int, Argument>
     */
    public function getArguments(): array
    {
        return array_values($this->arguments);
    }

    public function getArgument(string $name): ?Argument
    {
        return isset($this->arguments[$name]) ? $this->arguments[$name] : null;
    }

    public function requiresBearerAuth(): bool
    {
        return $this->hasFlag('bearer_auth');
    }

    public function capturesBearer(): bool
    {
        return $this->hasFlag('capture_bearer');
    }

    public function isPaginated(): bool
    {
        return $this->hasFlag('paginated');
    }

    public function isPrivate(): bool
    {
        return $this->hasFlag('private');
    }
}
