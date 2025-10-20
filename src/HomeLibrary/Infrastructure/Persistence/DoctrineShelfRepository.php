<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfRepository;
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

    public function findById(UuidInterface $id): ?Shelf
    {
        return parent::find($id);
    }

    /**
     * @return Shelf[]
     */
    public function search(?string $searchTerm): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'ASC');

        if ($searchTerm !== null) {
            $qb->andWhere('LOWER(s.name.value) LIKE :searchTerm')
                ->setParameter('searchTerm', sprintf('%%%s%%', mb_strtolower($searchTerm)));
        }

        /** @var Shelf[] $shelves */
        $shelves = $qb->getQuery()->getResult();

        return $shelves;
    }

    public function countBySearchTerm(?string $searchTerm): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');

        if ($searchTerm !== null) {
            $qb->andWhere('LOWER(s.name.value) LIKE :searchTerm')
                ->setParameter('searchTerm', sprintf('%%%s%%', mb_strtolower($searchTerm)));
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


