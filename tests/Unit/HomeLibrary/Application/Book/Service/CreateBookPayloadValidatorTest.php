<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\Book\Service;

use App\HomeLibrary\Application\Book\Service\CreateBookPayloadValidator;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\UI\Api\Book\Dto\CreateBookPayloadDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateBookPayloadValidatorTest extends TestCase
{
    private CreateBookPayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CreateBookPayloadValidator();
    }

    #[Test]
    public function itNormalizesValidPayload(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $dto = new CreateBookPayloadDto(
            title: '  The Witcher  ',
            author: "\tAndrzej Sapkowski\n",
            shelfId: $uuid,
            genreIds: [1, 2],
            isbn: '978-83-1234567-0',
            pageCount: '384',
            source: 'ai_recommendation',
            recommendationId: 42,
        );

        $result = $this->validator->validate($dto);

        self::assertSame('The Witcher', $result['title']);
        self::assertSame('Andrzej Sapkowski', $result['author']);
        self::assertSame($uuid, $result['shelfId']->toString());
        self::assertSame([1, 2], $result['genreIds']);
        self::assertSame('9788312345670', $result['isbn']);
        self::assertSame(384, $result['pageCount']);
        self::assertSame(BookSource::AI_RECOMMENDATION, $result['source']);
        self::assertSame(42, $result['recommendationId']);
    }

    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function itThrowsValidationExceptionForInvalidPayload(array $overrides, array $expectedErrors): void
    {
        $payload = array_merge(self::validPayloadData(), $overrides);

        $dto = new CreateBookPayloadDto(
            title: $payload['title'],
            author: $payload['author'],
            shelfId: $payload['shelfId'],
            genreIds: $payload['genreIds'],
            isbn: $payload['isbn'],
            pageCount: $payload['pageCount'],
            source: $payload['source'],
            recommendationId: $payload['recommendationId'],
        );

        try {
            $this->validator->validate($dto);
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame($expectedErrors, $exception->errors());
        }
    }

    /**
     * @return iterable<string, array{0: array, 1: array<string, list<string>>}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'title not string' => [
            ['title' => 123],
            ['title' => ['This value should be of type string.']],
        ];

        yield 'title blank' => [
            ['title' => '   '],
            ['title' => ['This value should not be blank.']],
        ];

        yield 'title too long' => [
            ['title' => str_repeat('a', 260)],
            ['title' => ['This value is too long. It should have 255 characters or less.']],
        ];

        yield 'author not string' => [
            ['author' => null],
            ['author' => ['This value should be of type string.']],
        ];

        yield 'shelfId type mismatch' => [
            ['shelfId' => 456],
            ['shelfId' => ['This value should be of type string.']],
        ];

        yield 'shelfId invalid uuid' => [
            ['shelfId' => 'not-a-uuid'],
            ['shelfId' => ['This is not a valid UUID.']],
        ];

        yield 'genreIds not array' => [
            ['genreIds' => '1,2'],
            ['genreIds' => ['This value should be of type array.']],
        ];

        yield 'genreIds too few' => [
            ['genreIds' => []],
            ['genreIds' => ['This collection should contain between 1 and 3 elements.']],
        ];

        yield 'genreIds not integer' => [
            ['genreIds' => [1, 'two']],
            ['genreIds' => ['Each genre identifier must be an integer.']],
        ];

        yield 'genreIds not positive' => [
            ['genreIds' => [0]],
            ['genreIds' => ['Each genre identifier must be a positive integer.']],
        ];

        yield 'genreIds duplicate' => [
            ['genreIds' => [1, 1]],
            ['genreIds' => ['Genre identifiers must be unique.']],
        ];

        yield 'isbn invalid length' => [
            ['isbn' => '12345'],
            ['isbn' => ['ISBN must contain 10 or 13 digits.']],
        ];

        yield 'isbn wrong type' => [
            ['isbn' => 1234567890],
            ['isbn' => ['This value should be of type string.']],
        ];

        yield 'pageCount wrong type' => [
            ['pageCount' => 'abc'],
            ['pageCount' => ['This value should be an integer.']],
        ];

        yield 'pageCount out of range' => [
            ['pageCount' => 0],
            ['pageCount' => ['This value should be between 1 and 50000.']],
        ];

        yield 'source not string' => [
            ['source' => 123],
            ['source' => ['This value should be of type string.']],
        ];

        yield 'source invalid value' => [
            ['source' => 'invalid'],
            ['source' => ['This value is not valid.']],
        ];

        yield 'recommendation missing for ai source' => [
            ['source' => 'ai_recommendation'],
            ['recommendationId' => ['This value is required for AI recommendations.']],
        ];

        yield 'recommendation not integer' => [
            ['source' => 'ai_recommendation', 'recommendationId' => 'abc'],
            ['recommendationId' => ['This value should be an integer.', 'This value is required for AI recommendations.']],
        ];

        yield 'recommendation not positive' => [
            ['source' => 'ai_recommendation', 'recommendationId' => 0],
            ['recommendationId' => ['This value should be a positive integer.', 'This value is required for AI recommendations.']],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     author: string,
     *     shelfId: string,
     *     genreIds: int[],
     *     isbn: ?string,
     *     pageCount: ?int,
     *     source: string|null,
     *     recommendationId: int|string|null
     * }
     */
    private static function validPayloadData(): array
    {
        return [
            'title' => 'Title',
            'author' => 'Author',
            'shelfId' => Uuid::uuid4()->toString(),
            'genreIds' => [1, 2],
            'isbn' => null,
            'pageCount' => null,
            'source' => null,
            'recommendationId' => null,
        ];
    }
}
