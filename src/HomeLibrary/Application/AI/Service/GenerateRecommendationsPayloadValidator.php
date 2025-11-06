<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Service;

use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\UI\Api\AI\Dto\GenerateRecommendationsPayloadDto;

final class GenerateRecommendationsPayloadValidator
{
    private const FIELD_INPUTS = 'inputs';
    private const FIELD_EXCLUDE_TITLES = 'excludeTitles';
    private const FIELD_MODEL = 'model';
    private const MODEL_MAX_LENGTH = 191;

    /**
     * @return array{
     *     inputs: string[],
     *     excludeTitles: string[],
     *     model: string|null,
     * }
     */
    public function validate(GenerateRecommendationsPayloadDto $payload): array
    {
        $issues = [];

        $inputs = $this->normalizeStringCollection($payload->inputs(), self::FIELD_INPUTS, false, $issues);
        $excludeTitles = $this->normalizeStringCollection($payload->excludeTitles(), self::FIELD_EXCLUDE_TITLES, true, $issues);
        $model = $this->normalizeModel($payload->model(), $issues);

        if ([] !== $issues) {
            throw ValidationException::withIssues($issues);
        }

        return [
            'inputs' => $inputs,
            'excludeTitles' => $excludeTitles,
            'model' => $model,
        ];
    }

    /**
     * @return string[]
     */
    private function normalizeStringCollection(mixed $value, string $field, bool $allowEmpty, array &$issues): array
    {
        if (null === $value && $allowEmpty) {
            return [];
        }

        if (!\is_array($value)) {
            $issues[] = [
                'parameter' => $field,
                'message' => 'This value should be of type array.',
            ];

            return [];
        }

        if (!$allowEmpty && [] === $value) {
            $issues[] = [
                'parameter' => $field,
                'message' => 'This collection should contain at least 1 element.',
            ];

            return [];
        }

        $normalized = [];

        foreach ($value as $index => $item) {
            if (!\is_string($item)) {
                $issues[] = [
                    'parameter' => \sprintf('%s[%d]', $field, $index),
                    'message' => 'This value should be of type string.',
                ];

                continue;
            }

            $trimmed = trim($item);

            if ('' === $trimmed) {
                $issues[] = [
                    'parameter' => \sprintf('%s[%d]', $field, $index),
                    'message' => 'This value should not be blank.',
                ];

                continue;
            }

            $key = strtolower($trimmed);

            if (!\array_key_exists($key, $normalized)) {
                $normalized[$key] = $trimmed;
            }
        }

        return array_values($normalized);
    }

    private function normalizeModel(mixed $value, array &$issues): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            $issues[] = [
                'parameter' => self::FIELD_MODEL,
                'message' => 'This value should be of type string.',
            ];

            return null;
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            return null;
        }

        if (mb_strlen($trimmed) > self::MODEL_MAX_LENGTH) {
            $issues[] = [
                'parameter' => self::FIELD_MODEL,
                'message' => \sprintf('This value is too long. It should have %d characters or less.', self::MODEL_MAX_LENGTH),
            ];

            return null;
        }

        return $trimmed;
    }
}
