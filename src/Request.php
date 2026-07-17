<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A request invokes a single Web Function endpoint over HTTP.
 *
 * @example
 *   $request = new Request("https://api.example.com/endpoint");
 *   $request->run(); // => ["a" => 1]
 */
class Request
{
    /**
     * The HTTP client used to execute requests. A callable with the signature:
     *
     *     callable(string $url, array<string, string> $headers, ?string $body): array{0: int, 1: string}
     *
     * - $url:     The full URL to request.
     * - $headers: HTTP headers as an associative array.
     * - $body:    The raw request body to POST, or `null` for a GET request.
     *
     * Must return a two-element array: `[$statusCode, $rawResponseBody]`.
     *
     * Defaults to a small cURL-based client. Swap it out (e.g. in tests) via {@see self::setHttpClient()}.
     *
     * @var callable|null
     */
    private static $httpClient = null;

    /** @var string */
    private $url;

    /** @var string|null */
    private $bearerAuth;

    /** @var string|null */
    private $version;

    /** @var array<string, mixed> */
    private $args;

    /**
     * @param array<string, mixed> $args
     */
    public function __construct(string $url, ?string $bearerAuth = null, ?string $version = null, array $args = [])
    {
        $this->url = $url;
        $this->bearerAuth = $bearerAuth;
        $this->version = $version;
        $this->args = $args;
    }

    /**
     * Sets a custom HTTP client. Pass `null` to restore the default cURL-based client.
     */
    public static function setHttpClient(?callable $client): void
    {
        self::$httpClient = $client;
    }

    /**
     * @return callable
     */
    public static function getHttpClient()
    {
        if (self::$httpClient === null) {
            self::$httpClient = function (string $url, array $headers, ?string $body): array {
                $ch = curl_init($url);

                $formattedHeaders = [];

                foreach ($headers as $name => $value) {
                    $formattedHeaders[] = "{$name}: {$value}";
                }

                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => $formattedHeaders,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                ]);

                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }

                $response = curl_exec($ch);

                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);

                    throw new \RuntimeException("HTTP request to {$url} failed: {$error}");
                }

                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                return [$status, $response];
            };
        }

        return self::$httpClient;
    }

    /**
     * Executes a request in one call.
     *
     * @param array<string, mixed> $args
     * @return mixed
     */
    public static function execute(string $url, ?string $bearerAuth = null, ?string $version = null, array $args = [])
    {
        return (new self($url, $bearerAuth, $version, $args))->run();
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getBearerAuth(): ?string
    {
        return $this->bearerAuth;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'webfunction-php/' . Version::VERSION,
            'Accept-Encoding' => 'gzip',
        ];

        if ($this->bearerAuth !== null) {
            $headers['Authorization'] = "Bearer {$this->bearerAuth}";
        }

        if ($this->version !== null) {
            $headers['Api-Version'] = $this->version;
        }

        return $headers;
    }

    /**
     * Returns this request as a pipeline step (`url`, `headers`, `body`).
     *
     * @return array{url: string, headers: array<string, string>, body: array<string, mixed>}
     */
    public function asPipelineStep(): array
    {
        return [
            'url' => $this->url,
            'headers' => $this->headers(),
            'body' => $this->args,
        ];
    }

    /**
     * Executes the request.
     *
     * When the response matches the pagination contract (`page`, `next`, `previous` keys), it is wrapped in
     * a {@see Page} so callers can navigate with {@see Page::nextPage()} and {@see Page::previousPage()}.
     *
     * @return mixed
     *
     * @throws UnexpectedStatusCodeError If the status code is not 200 or 400
     * @throws JsonParseError If the response body is not valid JSON
     * @throws BadRequestError If the response is a bad request (400)
     */
    public function run()
    {
        $client = self::getHttpClient();
        $response = $client($this->url, $this->headers(), json_encode($this->args, JSON_THROW_ON_ERROR));
        $status = $response[0];
        $body = $response[1];

        if (!in_array($status, [200, 400], true)) {
            throw new UnexpectedStatusCodeError(
                "Unexpected status code ({$status})",
                null,
                ['status_code' => $status, 'raw_body' => $body]
            );
        }

        try {
            $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonParseError(
                $e->getMessage(),
                null,
                ['status_code' => $status, 'raw_body' => $body, 'original_exception' => $e]
            );
        }

        if ($status === 400) {
            $code = 'WFN_BAD_REQUEST_ERROR';
            $message = 'Bad request';
            $details = ['body' => $result];

            if (is_array($result) && array_is_list($result) && count($result) === 3 && is_string($result[0]) && is_string($result[1])) {
                $code = $result[0];
                $message = $result[1];
                $details = $result[2];
            }

            throw new BadRequestError($message, $code, $details);
        }

        return Page::wrap($result, $this);
    }
}
