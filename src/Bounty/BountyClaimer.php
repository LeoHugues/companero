<?php

namespace App\Bounty;

use App\Calendar\Week;
use App\Entity\Bounty;
use App\Entity\Completion;
use App\Entity\Gift;
use App\Entity\PointEntry;
use App\Enum\BountyKind;
use App\Enum\PointReason;
use App\Repository\BountyRepository;
use Doctrine\ORM\EntityManagerInterface;

/** The first member to do a task with a surprise this week finds it: points, XP or a gift. */
final readonly class BountyClaimer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BountyRepository $bounties,
    ) {
    }

    /** @return ?Bounty the surprise just found, if there was one waiting (persisted, not flushed) */
    public function claim(Completion $completion): ?Bounty
    {
        $bounty = $this->bounties->findOneForWeek($completion->getTask(), Week::containing($completion->getCompletedAt())->start);
        if (null === $bounty || $bounty->isClaimed()) {
            return null;
        }
        $bounty->claim($completion);
        $member = $completion->getMember();
        $label = mb_substr('Surprise : '.$completion->getTask()->getTitle(), 0, 80);

        match ($bounty->getKind()) {
            BountyKind::Points => $this->entityManager->persist(new PointEntry($member, PointReason::Bounty, $bounty->getAmount(), $label, $completion->getCompletedAt(), $completion)),
            BountyKind::Xp => $this->entityManager->persist(new PointEntry($member, PointReason::BountyXp, $bounty->getAmount(), $label, $completion->getCompletedAt(), $completion)),
            default => $this->entityManager->persist(new Gift($member, $bounty->getKind()->gift(), $label, $completion->getCompletedAt())),
        };

        return $bounty;
    }
}
