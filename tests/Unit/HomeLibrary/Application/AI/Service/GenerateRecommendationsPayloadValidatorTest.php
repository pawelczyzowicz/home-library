<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\AI\Service;

use App\HomeLibrary\Application\AI\Service\GenerateRecommendationsPayloadValidator;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\UI\Api\AI\Dto\GenerateRecommendationsPayloadDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenerateRecommendationsPayloadValidatorTest extends TestCase
{
    private GenerateRecommendationsPayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new GenerateRecommendationsPayloadValidator();
    }

    #[Test]
    public function itNormalizesValidPayload(): void
    {
        $dto = new GenerateRecommendationsPayloadDto(
            inputs: ['  Title One  ', 'TITLE one', 'Title Two'],
            excludeTitles: ['   excluded  '],
            model: '  openrouter/model  ',
        );

        $result = $this->validator->validate($dto);

        self::assertSame(['Title One', 'Title Two'], $result['inputs']);
        self::assertSame(['excluded'], $result['excludeTitles']);
        self::assertSame('openrouter/model', $result['model']);
    }

    #[Test]
    public function itAllowsNullOptionalFields(): void
    {
        $dto = new GenerateRecommendationsPayloadDto(inputs: ['Title'], excludeTitles: null, model: null);

        $result = $this->validator->validate($dto);

        self::assertSame(['Title'], $result['inputs']);
        self::assertSame([], $result['excludeTitles']);
        self::assertNull($result['model']);
    }

    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function itThrowsValidationExceptionForInvalidPayload(GenerateRecommendationsPayloadDto $dto, array $expectedErrors): void
    {
        try {
            $this->validator->validate($dto);
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame($expectedErrors, $exception->errors());
        }
    }

    /**
     * @return iterable<string, array{0: GenerateRecommendationsPayloadDto, 1: array<string, list<string>>}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'inputs missing' => [
            new GenerateRecommendationsPayloadDto(inputs: null, excludeTitles: null, model: null),
            ['inputs' => ['This value should be of type array.']],
        ];

        yield 'inputs empty' => [
            new GenerateRecommendationsPayloadDto(inputs: [], excludeTitles: null, model: null),
            ['inputs' => ['This collection should contain at least 1 element.']],
        ];

        yield 'inputs non string entry' => [
            new GenerateRecommendationsPayloadDto(inputs: ['Title', 123], excludeTitles: null, model: null),
            ['inputs[1]' => ['This value should be of type string.']],
        ];

        yield 'inputs blank entry' => [
            new GenerateRecommendationsPayloadDto(inputs: [''], excludeTitles: null, model: null),
            ['inputs[0]' => ['This value should not be blank.']],
        ];

        yield 'excludeTitles wrong type' => [
            new GenerateRecommendationsPayloadDto(inputs: ['Title'], excludeTitles: 'foo', model: null),
            ['excludeTitles' => ['This value should be of type array.']],
        ];

        yield 'excludeTitles invalid entry' => [
            new GenerateRecommendationsPayloadDto(inputs: ['Title'], excludeTitles: [123], model: null),
            ['excludeTitles[0]' => ['This value should be of type string.']],
        ];

        yield 'model wrong type' => [
            new GenerateRecommendationsPayloadDto(inputs: ['Title'], excludeTitles: null, model: 123),
            ['model' => ['This value should be of type string.']],
        ];

        yield 'model too long' => [
            new GenerateRecommendationsPayloadDto(inputs: ['Title'], excludeTitles: null, model: str_repeat('a', 192)),
            ['model' => ['This value is too long. It should have 191 characters or less.']],
        ];
    }
}
