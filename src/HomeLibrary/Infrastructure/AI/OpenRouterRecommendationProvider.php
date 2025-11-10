<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Application\AI\IRecommendationProvider;
use App\HomeLibrary\Application\Genre\Query\ListGenresHandler;
use App\HomeLibrary\Application\Genre\Query\ListGenresResult;
use App\HomeLibrary\Domain\AI\RecommendationProposal;
use App\HomeLibrary\Domain\Genre\GenreName;
use App\HomeLibrary\Infrastructure\AI\Client\OpenRouterHttpClient;
use App\HomeLibrary\Infrastructure\AI\Exception\JsonSchemaNotSupportedException;
use App\HomeLibrary\Infrastructure\AI\Response\OpenRouterResponseParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @SuppressWarnings("PHPMD.ExcessiveClassComplexity") */
final class OpenRouterRecommendationProvider implements IRecommendationProvider
{
    private const MAX_GENRES = 3;
    private const MIN_GENRE_ID = 1;
    private const MAX_GENRE_ID = 15;
    private const MAX_REASON_LENGTH = 280;
    private const MAX_INPUTS_FOR_PROMPT = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ListGenresHandler $listGenresHandler,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $defaultModel,
        /**
         * @var array<string, mixed>
         */
        private readonly array $defaultParams = [],
        private readonly ?string $appReferer = null,
        private readonly ?string $appTitle = null,
    ) {}

    /**
     * @param string[] $inputs
     *
     * @return RecommendationProposal[]
     */
    public function generate(array $inputs): array
    {
        $normalizedInputs = $this->normalizeInputs($inputs);
        $genresCatalog = $this->fetchGenresCatalog();
        $seed = $this->computeSeed($normalizedInputs);

        $responseFormat = $this->buildResponseFormatSchema();
        $userMessage = $this->buildUserMessage($normalizedInputs, $genresCatalog);

        $messagesWithSchema = [
            ['role' => 'system', 'content' => $this->buildSystemMessage(false)],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $timeout = $this->extractTimeout();
        $httpClient = new OpenRouterHttpClient(
            $this->httpClient,
            $this->baseUrl,
            $this->buildHeaders(),
            $timeout,
        );
        $parser = new OpenRouterResponseParser(self::MIN_GENRE_ID, self::MAX_GENRE_ID, self::MAX_GENRES);

        $payload = $this->buildPayload($messagesWithSchema, $responseFormat, $normalizedInputs, $seed);

        try {
            $data = $httpClient->send($payload, true);
        } catch (JsonSchemaNotSupportedException $exception) {
            $messagesWithoutSchema = [
                ['role' => 'system', 'content' => $this->buildSystemMessage(true)],
                ['role' => 'user', 'content' => $userMessage],
            ];

            $fallbackPayload = $this->buildPayload($messagesWithoutSchema, null, $normalizedInputs, $seed);
            $data = $httpClient->send($fallbackPayload, false);
        }

        $recommendations = $parser->parseRecommendations($data);

        $proposals = [];

        foreach ($recommendations as $index => $item) {
            $genres = $item['genresId'];
            $reason = $item['reason'];
            $adaptedReason = $this->adaptReason($reason, $normalizedInputs);

            $proposals[] = new RecommendationProposal(
                $this->makeTempId($seed, $index),
                $item['title'],
                $item['author'],
                $genres,
                $adaptedReason,
            );
        }

        return $proposals;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function fetchGenresCatalog(): array
    {
        $result = $this->invokeListGenres();
        $catalog = [];

        foreach ($result->genres() as $genre) {
            $catalog[] = [
                'id' => $genre->id(),
                'name' => $this->normalizeGenreName($genre->name()),
            ];
        }

        return $catalog;
    }

    private function buildSystemMessage(bool $plainJsonFallback): string
    {
        $base = <<<TXT
Jesteś asystentem literackim. Zwróć dokładnie 3 rekomendacje książek jako JSON zgodny z dostarczonym schematem. Każda rekomendacja: title, author, genresId (1–3 wartości 1..15), reason (<= 280 znaków). genresId dobierz względem katalogu (id → name). Nie dodawaj pól poza schematem. Nie tłumacz i nie komentuj.
TXT;

        if ($plainJsonFallback) {
            $base .= "\nZWRÓĆ WYŁĄCZNIE JSON spełniający wymagania, bez dodatkowego tekstu.";
        }

        return $base;
    }

    /**
     * @param string[]                                 $inputs
     * @param array<int, array{id: int, name: string}> $genresCatalog
     */
    private function buildUserMessage(array $inputs, array $genresCatalog): string
    {
        $limitedInputs = \array_slice($inputs, 0, self::MAX_INPUTS_FOR_PROMPT);

        $lines = ['Wejściowe tytuły/autorzy:'];

        foreach ($limitedInputs as $input) {
            $lines[] = '- ' . $input;
        }

        if (0 === \count($limitedInputs)) {
            $lines[] = '- brak';
        }

        $lines[] = '';
        $lines[] = 'Katalog gatunków (id → name):';

        $pairs = array_map(
            static fn (array $genre): string => \sprintf('%d: %s', $genre['id'], $genre['name']),
            $genresCatalog,
        );

        $lines[] = implode(', ', $pairs);
        $lines[] = '';
        $lines[] = 'Zwróć 3 propozycje dopasowane do powyższych preferencji.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponseFormatSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'ai_recommendations',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['recommendations'],
                    'properties' => [
                        'recommendations' => [
                            'type' => 'array',
                            'minItems' => 3,
                            'maxItems' => 3,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['title', 'author', 'genresId', 'reason'],
                                'properties' => [
                                    'title' => ['type' => 'string', 'minLength' => 1],
                                    'author' => ['type' => 'string', 'minLength' => 1],
                                    'genresId' => [
                                        'type' => 'array',
                                        'minItems' => 1,
                                        'maxItems' => 3,
                                        'items' => ['type' => 'integer', 'minimum' => self::MIN_GENRE_ID, 'maximum' => self::MAX_GENRE_ID],
                                    ],
                                    'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => self::MAX_REASON_LENGTH],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed>|null        $responseFormat
     * @param string[]                         $inputs
     *
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, ?array $responseFormat, array $inputs, int $seed): array
    {
        $params = $this->prepareModelParams($inputs, $seed);

        $payload = [
            'model' => $this->defaultModel,
            'messages' => $messages,
        ];

        if (null !== $responseFormat) {
            $payload['response_format'] = $responseFormat;
        }

        /**
         * @var array<string, mixed> $merged
         */
        $merged = array_filter($payload + $params, static fn ($value): bool => null !== $value && '' !== $value);

        return $merged;
    }

    /**
     * @param string[] $inputs
     *
     * @return array<string, mixed>
     */
    private function prepareModelParams(array $inputs, int $seed): array
    {
        $params = $this->defaultParams;

        if (!\array_key_exists('seed', $params)) {
            $params['seed'] = $seed;
        } elseif (null === $params['seed'] || '' === (string) $params['seed']) {
            unset($params['seed']);
        } else {
            $params['seed'] = (int) $params['seed'];
        }

        if (isset($params['temperature']) && is_numeric($params['temperature'])) {
            $params['temperature'] = (float) $params['temperature'];
        }

        if (isset($params['top_p']) && is_numeric($params['top_p'])) {
            $params['top_p'] = (float) $params['top_p'];
        }

        if (isset($params['max_tokens']) && is_numeric($params['max_tokens'])) {
            $params['max_tokens'] = (int) $params['max_tokens'];
        }

        unset($params['timeout']);

        return $params;
    }

    /**
     * @param string[] $inputs
     */
    private function adaptReason(string $reason, array $inputs): string
    {
        $trimmed = trim($reason);

        if ('' === $trimmed) {
            throw new \RuntimeException('Field "reason" must be a non-empty string.');
        }

        $firstInput = $inputs[0] ?? null;

        if (\is_string($firstInput) && '' !== $firstInput) {
            $appendix = \sprintf(' Inspired by "%s".', $firstInput);
            $candidate = $trimmed . $appendix;

            if ($this->length($candidate) <= self::MAX_REASON_LENGTH) {
                $trimmed = $candidate;
            }
        }

        if ($this->length($trimmed) > self::MAX_REASON_LENGTH) {
            $trimmed = $this->truncate($trimmed, self::MAX_REASON_LENGTH);
        }

        return $trimmed;
    }

    private function makeTempId(int $seed, int $index): string
    {
        return \sprintf('or-%u-%d', $seed, $index + 1);
    }

    /**
     * @param string[] $inputs
     */
    private function normalizeInputs(array $inputs): array
    {
        $normalized = [];

        foreach ($inputs as $value) {
            $trimmed = trim($value);

            if ('' === $trimmed) {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return $normalized;
    }

    /**
     * @param string[] $inputs
     */
    private function computeSeed(array $inputs): int
    {
        $payload = json_encode(['inputs' => $inputs], \JSON_THROW_ON_ERROR);
        $crc = crc32($payload);

        return (int) (abs($crc) ?: 1);
    }

    private function normalizeGenreName(GenreName $name): string
    {
        $value = trim($name->value());

        if ($this->length($value) > 100) {
            $value = $this->truncate($value, 100);
        }

        return $value;
    }

    private function length(string $value): int
    {
        return \function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : \strlen($value);
    }

    private function truncate(string $value, int $maxLength): string
    {
        if ($this->length($value) <= $maxLength) {
            return $value;
        }

        return \function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength, 'UTF-8')
            : substr($value, 0, $maxLength);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if (null !== $this->appReferer && '' !== trim($this->appReferer)) {
            $headers['HTTP-Referer'] = trim($this->appReferer);
        }

        if (null !== $this->appTitle && '' !== trim($this->appTitle)) {
            $headers['X-Title'] = trim($this->appTitle);
        }

        return $headers;
    }

    private function extractTimeout(): ?float
    {
        if (!\array_key_exists('timeout', $this->defaultParams)) {
            return null;
        }

        $timeout = $this->defaultParams['timeout'];

        if (null === $timeout || '' === (string) $timeout) {
            return null;
        }

        if (!is_numeric($timeout)) {
            return null;
        }

        return (float) $timeout;
    }

    private function invokeListGenres(): ListGenresResult
    {
        $handler = $this->listGenresHandler;

        return $handler();
    }
}
