<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Domain\AI\AiRecommendationEvent;
use App\HomeLibrary\Domain\AI\RecommendationEventRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    public function countSuccessRate(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $conditions = [];
        $parameters = [];
        $types = [];

        if (null !== $from) {
            $conditions[] = 'created_at >= :from';
            $parameters['from'] = $from;
            $types['from'] = Types::DATETIME_IMMUTABLE;
        }

        if (null !== $to) {
            $conditions[] = 'created_at <= :to';
            $parameters['to'] = $to;
            $types['to'] = Types::DATETIME_IMMUTABLE;
        }

        $whereClause = '';

        if ([] !== $conditions) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql = <<<SQL
SELECT
    COUNT(*) AS total_events,
    COUNT(*) FILTER (WHERE jsonb_array_length(accepted_book_ids) > 0) AS accepted_events
FROM ai_recommendation_events
{$whereClause}
SQL;

        $row = $connection->fetchAssociative($sql, $parameters, $types) ?: [];

        return [
            'total_events' => (int) ($row['total_events'] ?? 0),
            'accepted_events' => (int) ($row['accepted_events'] ?? 0),
        ];
    }
}
