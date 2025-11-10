<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI\Response;

final class OpenRouterResponseParser
{
    public function __construct(
        private readonly int $minGenreId,
        private readonly int $maxGenreId,
        private readonly int $maxGenres,
    ) {}

    /**
     * @return array<int, array{title: string, author: string, genresId: int[], reason: string}>
     */
    public function parseRecommendations(array $response): array
    {
        $payload = $this->extractPayload($response);
        $recommendations = $payload['recommendations'] ?? null;

        $this->assertThreeRecommendations($recommendations);

        $normalized = [];

        foreach (array_values($recommendations) as $item) {
            $normalized[] = [
                'title' => $this->extractNonEmptyString($item, 'title'),
                'author' => $this->extractNonEmptyString($item, 'author'),
                'genresId' => $this->coerceGenresIds($item['genresId'] ?? null),
                'reason' => $this->extractNonEmptyString($item, 'reason'),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(array $data): array
    {
        $message = $this->extractMessage($data);

        if (isset($message['parsed'])) {
            return $this->assertArray($message['parsed'], 'OpenRouter parsed message must be an object.');
        }

        return $this->decodeContent($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMessage(array $data): array
    {
        $choices = $data['choices'] ?? null;

        if (!\is_array($choices) || !isset($choices[0]) || !\is_array($choices[0])) {
            throw new \RuntimeException('OpenRouter response is missing choices data.');
        }

        $message = $choices[0]['message'] ?? null;

        if (!\is_array($message)) {
            throw new \RuntimeException('OpenRouter response is missing message data.');
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function decodeContent(array $message): array
    {
        $content = $message['content'] ?? null;

        if (!\is_string($content) || '' === trim($content)) {
            throw new \RuntimeException('OpenRouter response does not contain parsable content.');
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('OpenRouter returned invalid JSON payload.', 0, $exception);
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException('OpenRouter response content must be a JSON object.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertArray(mixed $value, string $message): array
    {
        if (!\is_array($value)) {
            throw new \RuntimeException($message);
        }

        return $value;
    }

    private function assertThreeRecommendations(mixed $recommendations): void
    {
        if (!\is_array($recommendations) || 3 !== \count($recommendations)) {
            throw new \RuntimeException('OpenRouter must return exactly three recommendations.');
        }
    }

    private function extractNonEmptyString(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;

        if (!\is_string($value)) {
            throw new \RuntimeException(\sprintf('Field "%s" must be a string.', $field));
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \RuntimeException(\sprintf('Field "%s" must be a non-empty string.', $field));
        }

        return $trimmed;
    }

    /**
     * @return int[]
     */
    private function coerceGenresIds(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \RuntimeException('Field "genresId" must be an array.');
        }

        $normalized = [];

        foreach ($value as $rawGenre) {
            $genreId = $this->normalizeGenreId($rawGenre);
            $normalized[$genreId] = $genreId;
        }

        if (0 === \count($normalized) || \count($normalized) > $this->maxGenres) {
            throw new \RuntimeException('Field "genresId" must contain between 1 and 3 unique values.');
        }

        return array_values($normalized);
    }

    private function normalizeGenreId(mixed $value): int
    {
        if (\is_int($value)) {
            $genreId = $value;
        } elseif (\is_string($value) && '' !== trim($value) && ctype_digit(trim($value))) {
            $genreId = (int) trim($value);
        } else {
            throw new \RuntimeException('Each genresId value must be an integer.');
        }

        if ($genreId < $this->minGenreId || $genreId > $this->maxGenreId) {
            throw new \RuntimeException(\sprintf(
                'Each genresId value must be between %d and %d.',
                $this->minGenreId,
                $this->maxGenreId,
            ));
        }

        return $genreId;
    }
}
