<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

final class DoctrineLibraryRepository extends ServiceEntityRepository implements LibraryRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Library::class);
    }

    public function save(Library $library): void
    {
        $em = $this->getEntityManager();
        $em->persist($library);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Library
    {
        return parent::find($id);
    }

    public function existsByName(string $name): bool
    {
        $count = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.name.value = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findByName(string $name): ?Library
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.name.value = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
