<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\Book\Service;

use App\HomeLibrary\Application\Book\Service\ListBooksParameterValidator;
use App\HomeLibrary\Application\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListBooksParameterValidatorTest extends TestCase
{
    private ListBooksParameterValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ListBooksParameterValidator();
    }

    #[Test]
    public function itNormalizesValidParameters(): void
    {
        $result = $this->validator->validate([
            'q' => '  Sapkowski  ',
            'shelfId' => '00000000-0000-0000-0000-000000000001',
            'genreIds' => '1,2, 2,3',
            'limit' => '50',
            'offset' => '10',
            'sort' => 'author',
            'order' => 'ASC',
        ]);

        self::assertSame('Sapkowski', $result['q']);
        self::assertSame('00000000-0000-0000-0000-000000000001', $result['shelfId']?->toString());
        self::assertSame([1, 2, 3], $result['genreIds']);
        self::assertSame(50, $result['limit']);
        self::assertSame(10, $result['offset']);
        self::assertSame('author', $result['sort']);
        self::assertSame('asc', $result['order']);
    }

    #[Test]
    public function itRespectsDefaultValues(): void
    {
        $result = $this->validator->validate([
            'q' => null,
            'shelfId' => null,
            'genreIds' => '',
            'limit' => null,
            'offset' => null,
            'sort' => null,
            'order' => null,
        ]);

        self::assertNull($result['q']);
        self::assertNull($result['shelfId']);
        self::assertSame([], $result['genreIds']);
        self::assertSame(20, $result['limit']);
        self::assertSame(0, $result['offset']);
        self::assertSame('createdAt', $result['sort']);
        self::assertSame('desc', $result['order']);
    }

    #[Test]
    #[DataProvider('invalidParametersProvider')]
    public function itThrowsValidationExceptionForInvalidParameters(array $input, array $expectedErrors): void
    {
        try {
            $this->validator->validate($input);
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame($expectedErrors, $exception->errors());
        }
    }

    /** @return iterable<string, array{0: array, 1: array}> */
    public static function invalidParametersProvider(): iterable
    {
        yield 'query too long' => [
            [
                'q' => str_repeat('a', 260),
            ],
            [
                'q' => ['Parameter "q" must not exceed 255 characters.'],
            ],
        ];

        yield 'invalid shelf id format' => [
            [
                'shelfId' => 123,
            ],
            [
                'shelfId' => ['Parameter "shelfId" must be a string UUID.'],
            ],
        ];

        yield 'invalid shelf id value' => [
            [
                'shelfId' => 'not-uuid',
            ],
            [
                'shelfId' => ['Parameter "shelfId" must be a valid UUID.'],
            ],
        ];

        yield 'invalid genreIds format' => [
            [
                'genreIds' => ['foo'],
            ],
            [
                'genreIds' => ['Parameter "genreIds" must be a comma-separated string of integers.'],
            ],
        ];

        yield 'negative genre id' => [
            [
                'genreIds' => '1,-5',
            ],
            [
                'genreIds' => ['Parameter "genreIds" must contain positive integers.'],
            ],
        ];

        yield 'limit below minimal' => [
            [
                'limit' => '0',
            ],
            [
                'limit' => ['Parameter "limit" must be at least 1.'],
            ],
        ];

        yield 'offset negative' => [
            [
                'offset' => '-5',
            ],
            [
                'offset' => ['Parameter "offset" must be a non-negative integer.'],
            ],
        ];

        yield 'invalid sort' => [
            [
                'sort' => 'invalid',
            ],
            [
                'sort' => ['Parameter "sort" must be one of: title, author, createdAt.'],
            ],
        ];

        yield 'invalid order' => [
            [
                'order' => 'up',
            ],
            [
                'order' => ['Parameter "order" must be one of: asc, desc.'],
            ],
        ];
    }
}
