<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Application\Genre\Query\ListGenresHandler;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreName;
use App\HomeLibrary\Domain\Genre\GenreRepository;
use App\HomeLibrary\Infrastructure\AI\OpenRouterRecommendationProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenRouterRecommendationProviderTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_PARAMS = [
        'temperature' => 0.6,
        'top_p' => 0.95,
        'max_tokens' => 600,
        'seed' => null,
        'timeout' => 20,
    ];

    #[Test]
    public function itGeneratesProposalsFromParsedResponse(): void
    {
        $payload = [
            'choices' => [
                [
                    'message' => [
                        'parsed' => [
                            'recommendations' => [
                                [
                                    'title' => 'The Grace of Kings',
                                    'author' => 'Ken Liu',
                                    'genresId' => [2, 5],
                                    'reason' => 'Epic silk-punk saga with political intrigue.',
                                ],
                                [
                                    'title' => 'The Priory of the Orange Tree',
                                    'author' => 'Samantha Shannon',
                                    'genresId' => [2],
                                    'reason' => 'Standalone fantasy with richly developed worldbuilding.',
                                ],
                                [
                                    'title' => 'Hyperion',
                                    'author' => 'Dan Simmons',
                                    'genresId' => [5, 12],
                                    'reason' => 'Layered sci-fi pilgrimage blending mystery and horror.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            json_encode($payload, \JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        ));

        $provider = $this->createProvider($httpClient);
        $inputs = ['Dune', 'The Hobbit'];

        $proposals = $provider->generate($inputs);

        self::assertCount(3, $proposals);

        $expectedSeed = (int) (abs(crc32(json_encode(['inputs' => $inputs], \JSON_THROW_ON_ERROR))) ?: 1);

        self::assertSame(\sprintf('or-%u-1', $expectedSeed), $proposals[0]->tempId());
        self::assertSame('The Grace of Kings', $proposals[0]->title());
        self::assertSame('Ken Liu', $proposals[0]->author());
        self::assertSame([2, 5], $proposals[0]->genresId());
        self::assertSame('Epic silk-punk saga with political intrigue. Inspired by "Dune".', $proposals[0]->reason());
    }

    #[Test]
    public function itFallsBackWhenJsonSchemaNotSupported(): void
    {
        $successPayload = [
            'choices' => [
                [
                    'message' => [
                        'parsed' => [
                            'recommendations' => array_fill(0, 3, [
                                'title' => 'Sample Title',
                                'author' => 'Sample Author',
                                'genresId' => [2],
                                'reason' => 'Sample reason.',
                            ]),
                        ],
                    ],
                ],
            ],
        ];

        $requests = [];
        $callCounter = 0;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$callCounter, $successPayload): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            if (0 === $callCounter) {
                ++$callCounter;

                return new MockResponse(
                    json_encode(['error' => ['message' => 'Model does not support response_format json_schema']], \JSON_THROW_ON_ERROR),
                    ['http_code' => 400],
                );
            }

            ++$callCounter;

            return new MockResponse(
                json_encode($successPayload, \JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $provider = $this->createProvider($httpClient);
        $proposals = $provider->generate(['Dune']);

        self::assertCount(3, $proposals);
        self::assertSame(2, $callCounter);

        self::assertArrayHasKey('body', $requests[0]['options']);
        self::assertArrayHasKey('body', $requests[1]['options']);

        $firstPayload = json_decode($requests[0]['options']['body'], true, 512, \JSON_THROW_ON_ERROR);
        $secondPayload = json_decode($requests[1]['options']['body'], true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($firstPayload);
        self::assertArrayHasKey('response_format', $firstPayload);
        self::assertIsArray($secondPayload);
        self::assertArrayNotHasKey('response_format', $secondPayload);
        self::assertSame(20.0, $requests[0]['options']['timeout']);
    }

    #[Test]
    public function itThrowsWhenRecommendationCountIsInvalid(): void
    {
        $invalidPayload = [
            'choices' => [
                [
                    'message' => [
                        'parsed' => [
                            'recommendations' => [
                                [
                                    'title' => 'Only One',
                                    'author' => 'Author A',
                                    'genresId' => [2],
                                    'reason' => 'Reason one.',
                                ],
                                [
                                    'title' => 'Only Two',
                                    'author' => 'Author B',
                                    'genresId' => [5],
                                    'reason' => 'Reason two.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            json_encode($invalidPayload, \JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        ));

        $provider = $this->createProvider($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenRouter must return exactly three recommendations.');

        $provider->generate(['Dune']);
    }

    private function createProvider(HttpClientInterface $httpClient, ?array $defaultParams = null): OpenRouterRecommendationProvider
    {
        $params = $defaultParams ?? self::DEFAULT_PARAMS;

        return new OpenRouterRecommendationProvider(
            $httpClient,
            $this->createGenresHandler(),
            'test-api-key',
            'https://openrouter.ai/api/v1',
            'openai/gpt-4o-mini',
            $params,
            'http://localhost',
            'Home Library',
        );
    }

    private function createGenresHandler(): ListGenresHandler
    {
        $genres = [
            new Genre(1, new GenreName('kryminał')),
            new Genre(2, new GenreName('fantasy')),
            new Genre(5, new GenreName('sci-fi')),
            new Genre(12, new GenreName('thriller')),
        ];

        $repository = new class ($genres) implements GenreRepository {
            /**
             * @param Genre[] $genres
             */
            public function __construct(private readonly array $genres) {}

            /**
             * @return Genre[]
             */
            public function findByIds(array $ids): array
            {
                $lookup = array_flip($ids);

                return array_values(array_filter(
                    $this->genres,
                    static fn (Genre $genre): bool => isset($lookup[$genre->id()]),
                ));
            }

            /**
             * @return Genre[]
             */
            public function findAllOrderedByName(): array
            {
                return $this->genres;
            }
        };

        return new ListGenresHandler($repository);
    }
}
