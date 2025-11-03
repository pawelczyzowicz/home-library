<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineGenreRepository extends ServiceEntityRepository implements GenreRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Genre::class);
    }

    /**
     * @param int[] $ids
     *
     * @return Genre[]
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Genre[] $genres */
        $genres = $this->createQueryBuilder('g')
            ->andWhere('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return $genres;
    }

    public function findAllOrderedByName(): array
    {
        /** @var Genre[] $genres */
        $genres = $this->createQueryBuilder('g')
            ->orderBy('g.name.value', 'ASC')
            ->getQuery()
            ->getResult();

        return $genres;
    }
}
