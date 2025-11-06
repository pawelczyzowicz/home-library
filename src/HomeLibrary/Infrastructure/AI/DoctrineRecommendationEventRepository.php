<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Domain\AI\AiRecommendationEvent;
use App\HomeLibrary\Domain\AI\RecommendationEventRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

final class DoctrineRecommendationEventRepository extends ServiceEntityRepository implements RecommendationEventRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiRecommendationEvent::class);
    }

    public function save(AiRecommendationEvent $event): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($event);
        $entityManager->flush();
    }

    public function findById(int $id): ?AiRecommendationEvent
    {
        return parent::find($id);
    }

    public function findOwnedBy(int $id, ?UuidInterface $userId): ?AiRecommendationEvent
    {
        $qb = $this->createQueryBuilder('event')
            ->andWhere('event.id = :id')
            ->setParameter('id', $id);

        if (null === $userId) {
            $qb->andWhere('event.userId IS NULL');
        } else {
            $qb->andWhere('event.userId = :userId')
                ->setParameter('userId', $userId, 'uuid');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
