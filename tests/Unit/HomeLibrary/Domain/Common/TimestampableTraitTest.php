<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Common;

use App\HomeLibrary\Domain\Common\TimestampableTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimestampableTraitTest extends TestCase
{
    #[Test]
    public function itInitializesTimestampsOnPrePersist(): void
    {
        $entity = new class () {
            use TimestampableTrait;

            public function initialize(): void
            {
                $this->initializeTimestamps();
            }
        };

        $entity->initialize();

        $createdAt = $entity->createdAt();
        $updatedAt = $entity->updatedAt();

        self::assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        self::assertSame($createdAt->getTimestamp(), $updatedAt->getTimestamp());
    }

    #[Test]
    public function itUpdatesTimestampOnPreUpdate(): void
    {
        $entity = new class () {
            use TimestampableTrait;

            public function initialize(): void
            {
                $this->initializeTimestamps();
            }

            public function touch(): void
            {
                $this->touchUpdatedAt();
            }
        };

        $entity->initialize();

        $reflection = new \ReflectionClass($entity);
        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setAccessible(true);

        /** @var \DateTimeImmutable $originalUpdatedAt */
        $originalUpdatedAt = $updatedAtProperty->getValue($entity);
        $downgradedUpdatedAt = $originalUpdatedAt->modify('-3 minutes');
        $updatedAtProperty->setValue($entity, $downgradedUpdatedAt);

        $entity->touch();

        /** @var \DateTimeImmutable $freshUpdatedAt */
        $freshUpdatedAt = $updatedAtProperty->getValue($entity);

        self::assertInstanceOf(\DateTimeImmutable::class, $originalUpdatedAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $freshUpdatedAt);
        self::assertGreaterThan($downgradedUpdatedAt->getTimestamp(), $freshUpdatedAt->getTimestamp());
        self::assertGreaterThanOrEqual($originalUpdatedAt->getTimestamp(), $freshUpdatedAt->getTimestamp());
    }
}
