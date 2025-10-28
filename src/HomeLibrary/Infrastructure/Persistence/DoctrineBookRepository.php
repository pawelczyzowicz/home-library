<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Application\Book\Query\ListBooksResult;
use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

final class DoctrineBookRepository extends ServiceEntityRepository implements BookRepository
{
    private const SORT_FIELD_MAP = [
        'title' => 'b.title.value',
        'author' => 'b.author.value',
        'createdAt' => 'b.createdAt',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * @param int[] $genreIds
     */
    public function search(
        ?string $searchTerm,
        ?UuidInterface $shelfId,
        array $genreIds,
        int $limit,
        int $offset,
        string $sort,
        string $order,
    ): ListBooksResult {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.shelf', 's')
            ->addSelect('s')
            ->orderBy(self::SORT_FIELD_MAP[$sort], 'asc' === $order ? 'ASC' : 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (null !== $searchTerm) {
            $qb->andWhere('LOWER(b.title.value) LIKE :term OR LOWER(b.author.value) LIKE :term')
                ->setParameter('term', \sprintf('%%%s%%', mb_strtolower($searchTerm)));
        }

        if (null !== $shelfId) {
            $qb->andWhere('b.shelf = :shelfId')
                ->setParameter('shelfId', $shelfId);
        }

        if ([] !== $genreIds) {
            $qb->andWhere('EXISTS (
                SELECT 1
                FROM App\\HomeLibrary\\Domain\\Book\\Book b_genre
                JOIN b_genre.genres g_filter
                WHERE b_genre = b AND g_filter.id IN (:genreIds)
            )')
                ->setParameter('genreIds', $genreIds);
        }

        $paginator = new Paginator($qb);

        $books = iterator_to_array($paginator->getIterator());
        $this->loadGenres($books);

        return new ListBooksResult(
            books: $books,
            total: \count($paginator),
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * @param Book[] $books
     */
    private function loadGenres(array $books): void
    {
        if ([] === $books) {
            return;
        }

        $ids = array_map(static fn (Book $book): string => $book->id()->toString(), $books);

        $this->createQueryBuilder('b')
            ->select('b', 'g')
            ->leftJoin('b.genres', 'g')
            ->andWhere('b.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
