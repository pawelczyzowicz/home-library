<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\AI\Service;

use App\HomeLibrary\Application\AI\Service\AcceptRecommendationPayloadValidator;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\UI\Api\AI\Dto\AcceptRecommendationPayloadDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class AcceptRecommendationPayloadValidatorTest extends TestCase
{
    private AcceptRecommendationPayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AcceptRecommendationPayloadValidator();
    }

    #[Test]
    public function itValidatesBookId(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $result = $this->validator->validate(new AcceptRecommendationPayloadDto($uuid));

        self::assertSame($uuid, $result['bookId']->toString());
    }

    #[Test]
    public function itGuardsAgainstNonStringBookId(): void
    {
        try {
            $this->validator->validate(new AcceptRecommendationPayloadDto(123));
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame(['bookId' => ['This value should be of type string.']], $exception->errors());
        }
    }

    #[Test]
    public function itGuardsAgainstBlankBookId(): void
    {
        try {
            $this->validator->validate(new AcceptRecommendationPayloadDto('  '));
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame(['bookId' => ['This value should not be blank.']], $exception->errors());
        }
    }

    #[Test]
    public function itGuardsAgainstInvalidUuid(): void
    {
        try {
            $this->validator->validate(new AcceptRecommendationPayloadDto('not-a-uuid'));
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame(['bookId' => ['This is not a valid UUID.']], $exception->errors());
        }
    }
}
