<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineUserRepository extends ServiceEntityRepository implements UserRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function existsByEmail(string $emailLower): bool
    {
        $count = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.email.value = :email')
            ->setParameter('email', mb_strtolower($emailLower))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findByEmail(string $emailLower): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email.value = :email')
            ->setParameter('email', mb_strtolower($emailLower))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
