<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI\Client;

use App\HomeLibrary\Infrastructure\AI\Exception\JsonSchemaNotSupportedException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OpenRouterHttpClient
{
    private const RETRY_BACKOFF_MIN_MS = 500;
    private const RETRY_BACKOFF_MAX_MS = 800;
    private const MAX_RETRY_429 = 1;
    private const MAX_RETRY_5XX = 2;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly array $headers,
        private readonly ?float $timeout = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws JsonSchemaNotSupportedException
     */
    public function send(array $payload, bool $withJsonSchema): array
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/chat/completions';
        $retry429 = 0;
        $retry5xx = 0;

        while (true) {
            $response = $this->request($endpoint, $payload);
            $statusCode = $response->getStatusCode();
            $data = $this->decodeOrFallback($response, $statusCode);

            if (401 === $statusCode || 403 === $statusCode) {
                throw new \RuntimeException('Unauthorized request to OpenRouter API.');
            }

            if (429 === $statusCode && $retry429 < self::MAX_RETRY_429) {
                ++$retry429;
                $this->backoff();
                continue;
            }

            if ($statusCode >= 500 && $statusCode < 600 && $retry5xx < self::MAX_RETRY_5XX) {
                ++$retry5xx;
                $this->backoff();
                continue;
            }

            if ($statusCode >= 400) {
                $message = $this->extractErrorMessage($data, $statusCode);

                if ($withJsonSchema && 400 === $statusCode && $this->isJsonSchemaUnsupported($message)) {
                    throw new JsonSchemaNotSupportedException($message);
                }

                throw new \RuntimeException($message);
            }

            return $data;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(string $endpoint, array $payload): ResponseInterface
    {
        $options = [
            'headers' => array_filter($this->headers),
            'json' => $payload,
        ];

        if (null !== $this->timeout) {
            $options['timeout'] = $this->timeout;
        }

        try {
            return $this->httpClient->request('POST', $endpoint, $options);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Unable to contact OpenRouter API.', 0, $exception);
        }
    }

    private function decodeOrFallback(ResponseInterface $response, int $statusCode): array
    {
        try {
            /**
             * @var array<string, mixed> $decoded
             */
            $decoded = $response->toArray(false);

            return $decoded;
        } catch (DecodingExceptionInterface|TransportExceptionInterface $exception) {
            if ($statusCode >= 200 && $statusCode < 300) {
                throw new \RuntimeException('Failed to decode OpenRouter response.', 0, $exception);
            }

            return [];
        }
    }

    private function backoff(): void
    {
        $milliseconds = random_int(self::RETRY_BACKOFF_MIN_MS, self::RETRY_BACKOFF_MAX_MS);
        usleep($milliseconds * 1000);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractErrorMessage(array $data, int $statusCode): string
    {
        $message = null;

        if (isset($data['error'])) {
            if (\is_string($data['error'])) {
                $message = $data['error'];
            } elseif (\is_array($data['error'])) {
                $message = $data['error']['message'] ?? $data['error']['detail'] ?? null;
            }
        }

        if (null === $message && isset($data['message']) && \is_string($data['message'])) {
            $message = $data['message'];
        }

        if (null === $message) {
            $message = \sprintf('OpenRouter request failed with status %d.', $statusCode);
        }

        return trim($message);
    }

    private function isJsonSchemaUnsupported(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'json_schema')
            || str_contains($normalized, 'response_format');
    }
}
