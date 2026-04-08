<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitUmamiAdmin;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the Umami Analytics REST API.
 *
 * Supports two authentication strategies:
 * 1. API Key (preferred) — sent via `x-umami-api-key` header
 * 2. Username/Password (fallback) — logs in via POST /api/auth/login, caches JWT token
 *
 * All credential values are resolved lazily from constructor args or getenv(),
 * enabling hot-reload after CredentialTool::set() without restarting.
 */
final class UmamiClient
{
    private const int TIMEOUT = 30;

    private HttpClientInterface $httpClient;

    /** Cached JWT token from username/password login. */
    private string $jwtToken = '';

    public function __construct(
        private readonly string $apiUrl = '',
        private readonly string $apiKey = '',
        private readonly string $username = '',
        private readonly string $password = '',
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create(['timeout' => self::TIMEOUT]);
    }

    /**
     * Factory — reads all credentials from environment variables.
     */
    public static function fromEnv(): self
    {
        return new self(
            apiUrl: self::envString('UMAMI_API_URL'),
            apiKey: self::envString('UMAMI_API_KEY'),
            username: self::envString('UMAMI_USERNAME'),
            password: self::envString('UMAMI_PASSWORD'),
        );
    }

    /**
     * GET request.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, query: $query);
    }

    /**
     * POST request.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, body: $body);
    }

    /**
     * PUT request.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, body: $body);
    }

    /**
     * DELETE request.
     *
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Verify authentication by calling GET /api/auth/verify.
     *
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        return $this->get('/auth/verify');
    }

    /**
     * Get the resolved base URL for use in tracking code generation.
     */
    public function resolvedBaseUrl(): string
    {
        return $this->resolveApiUrl();
    }

    /**
     * Convert an ISO 8601 date string to Unix millisecond timestamp.
     * If the input is already numeric, returns it as-is.
     */
    public static function toTimestamp(string $date): int
    {
        if (is_numeric($date)) {
            return (int) $date;
        }

        $ts = strtotime($date);

        if ($ts === false) {
            throw new \InvalidArgumentException(sprintf('Invalid date format: %s', $date));
        }

        return $ts * 1000;
    }

    /**
     * Execute an HTTP request against the Umami API.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
    ): array {
        $baseUrl = $this->resolveApiUrl();

        if ($baseUrl === '') {
            throw new \RuntimeException('UMAMI_API_URL is not configured.');
        }

        $url = rtrim($baseUrl, '/') . '/api' . $path;

        $options = [
            'headers' => $this->buildAuthHeaders(),
        ];

        // Filter out empty/null query params
        $filteredQuery = array_filter($query, static fn (mixed $v): bool => $v !== null && $v !== '');

        if ($filteredQuery !== []) {
            $options['query'] = $filteredQuery;
        }

        if ($body !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);

            $statusCode = $response->getStatusCode();

            // DELETE often returns 200 with empty body
            $content = $response->getContent();

            if ($content === '') {
                return ['ok' => true, 'status' => $statusCode];
            }

            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                return ['ok' => true, 'data' => $data, 'status' => $statusCode];
            }

            return $data;
        } catch (HttpExceptionInterface $e) {
            $body = $this->extractErrorBody($e);
            $code = $e->getResponse()->getStatusCode();

            throw new \RuntimeException(sprintf('Umami API error (HTTP %d): %s', $code, $body));
        }
    }

    /**
     * Build auth headers — API key takes priority, then JWT token from login.
     *
     * @return array<string, string>
     */
    private function buildAuthHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $apiKey = $this->resolveApiKey();

        if ($apiKey !== '') {
            $headers['x-umami-api-key'] = $apiKey;

            return $headers;
        }

        // Fallback: username/password JWT login
        if ($this->jwtToken === '') {
            $this->login();
        }

        if ($this->jwtToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->jwtToken;
        }

        return $headers;
    }

    /**
     * Authenticate via POST /api/auth/login and cache the JWT token.
     */
    private function login(): void
    {
        $username = $this->resolveUsername();
        $password = $this->resolvePassword();

        if ($username === '' || $password === '') {
            return;
        }

        $baseUrl = $this->resolveApiUrl();

        if ($baseUrl === '') {
            return;
        }

        $url = rtrim($baseUrl, '/') . '/api/auth/login';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'username' => $username,
                    'password' => $password,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (is_array($data) && isset($data['token'])) {
                $this->jwtToken = (string) $data['token'];
            }
        } catch (\Throwable) {
            // Login failed — jwtToken stays empty, auth headers will be sent without token
        }
    }

    private function resolveApiUrl(): string
    {
        if ($this->apiUrl !== '') {
            return $this->apiUrl;
        }

        return self::envString('UMAMI_API_URL');
    }

    private function resolveApiKey(): string
    {
        if ($this->apiKey !== '') {
            return $this->apiKey;
        }

        return self::envString('UMAMI_API_KEY');
    }

    private function resolveUsername(): string
    {
        if ($this->username !== '') {
            return $this->username;
        }

        return self::envString('UMAMI_USERNAME');
    }

    private function resolvePassword(): string
    {
        if ($this->password !== '') {
            return $this->password;
        }

        return self::envString('UMAMI_PASSWORD');
    }

    /**
     * Extract a readable error body from an HTTP exception response.
     */
    private function extractErrorBody(HttpExceptionInterface $e): string
    {
        try {
            $body = $e->getResponse()->getContent(false);
            $decoded = json_decode($body, true);

            if (is_array($decoded)) {
                return $decoded['message'] ?? $decoded['error'] ?? $decoded['detail'] ?? $body;
            }

            return mb_substr($body, 0, 500);
        } catch (\Throwable) {
            return $e->getMessage();
        }
    }

    private static function envString(string $key): string
    {
        $value = getenv($key);

        return $value !== false ? $value : '';
    }
}
