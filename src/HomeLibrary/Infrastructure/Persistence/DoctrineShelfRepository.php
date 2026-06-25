<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

class DoctrineShelfRepository extends ServiceEntityRepository implements ShelfRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shelf::class);
    }

    public function save(Shelf $shelf): void
    {
        $em = $this->getEntityManager();
        $em->persist($shelf);
        $em->flush();
    }

    public function findById(UuidInterface $id, ?UuidInterface $libraryId = null): ?Shelf
    {
        if (null === $libraryId) {
            return parent::find($id);
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->andWhere('s.library = :libraryId')
            ->setParameter('id', $id)
            ->setParameter('libraryId', $libraryId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Shelf[]
     */
    public function search(UuidInterface $libraryId, ?string $searchTerm, ?bool $systemOnly = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.library = :libraryId')
            ->setParameter('libraryId', $libraryId)
            ->orderBy('s.createdAt', 'ASC');

        if (null !== $systemOnly) {
            $qb->andWhere('s.isSystem.isSystem = :isSystem')
                ->setParameter('isSystem', $systemOnly, Types::BOOLEAN);
        }

        if (null !== $searchTerm) {
            $qb->andWhere('LOWER(s.name.value) LIKE :searchTerm')
                ->setParameter('searchTerm', \sprintf('%%%s%%', mb_strtolower($searchTerm)));
        }

        /** @var Shelf[] $shelves */
        $shelves = $qb->getQuery()->getResult();

        return $shelves;
    }

    public function countBySearchTerm(UuidInterface $libraryId, ?string $searchTerm, ?bool $systemOnly = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.library = :libraryId')
            ->setParameter('libraryId', $libraryId);

        if (null !== $systemOnly) {
            $qb->andWhere('s.isSystem.isSystem = :isSystem')
                ->setParameter('isSystem', $systemOnly, Types::BOOLEAN);
        }

        if (null !== $searchTerm) {
            $qb->andWhere('LOWER(s.name.value) LIKE :searchTerm')
                ->setParameter('searchTerm', \sprintf('%%%s%%', mb_strtolower($searchTerm)));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function remove(Shelf $shelf): void
    {
        $em = $this->getEntityManager();
        $em->remove($shelf);
        $em->flush();
    }
}
