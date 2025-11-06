<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Service;

use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\UI\Api\AI\Dto\AcceptRecommendationPayloadDto;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class AcceptRecommendationPayloadValidator
{
    /**
     * @return array{bookId: UuidInterface}
     */
    public function validate(AcceptRecommendationPayloadDto $payload): array
    {
        $value = $payload->bookId();

        if (!\is_string($value)) {
            throw ValidationException::withMessage('bookId', 'This value should be of type string.');
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw ValidationException::withMessage('bookId', 'This value should not be blank.');
        }

        try {
            $uuid = Uuid::fromString($trimmed);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessage('bookId', 'This is not a valid UUID.');
        }

        return ['bookId' => $uuid];
    }
}
