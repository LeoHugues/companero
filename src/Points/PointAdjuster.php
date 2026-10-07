<?php

namespace App\Points;

use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Enum\PointReason;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** "This time it was more (or less) work than usual": anyone can nudge the points of a completion. */
final readonly class PointAdjuster
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function adjust(Completion $completion, Member $author, int $delta): void
    {
        if (!\in_array($delta, [-1, 1], true)) {
            throw new \InvalidArgumentException('Points are adjusted one at a time.');
        }

        $this->entityManager->persist(new PointEntry(
            $completion->getMember(),
            PointReason::Adjustment,
            $delta,
            \sprintf('%s (%s par %s)', $completion->getTask()->getTitle(), $delta > 0 ? 'plus' : 'moins', $author->getName()),
            $this->clock->now(),
            $completion,
        ));
        $this->entityManager->flush();
    }
}
