<?php

namespace App\Reward;

use App\Entity\Boost;
use App\Entity\Gift;
use App\Entity\Member;
use App\Entity\Pet;
use App\Enum\BoostKind;
use App\Enum\GiftKind;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Activates, offers or hands out a gift. */
final readonly class GiftUser
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BoostResolver $boosts,
        private ClockInterface $clock,
    ) {
    }

    /** @param ?Member $friend required for a targeted boost, never the owner */
    public function activate(Gift $gift, ?Member $friend = null): Boost
    {
        $this->assertUsable($gift);
        $owner = $gift->getOwner();
        $now = $this->clock->now();

        $boost = match ($gift->getKind()) {
            GiftKind::TeamBoost => new Boost($owner->getHousehold(), BoostKind::Points, null, $owner, $now),
            GiftKind::XpBoost => new Boost($owner->getHousehold(), BoostKind::Xp, $owner, $owner, $now),
            GiftKind::FriendBoost => null !== $friend && $friend !== $owner && $friend->belongsTo($owner->getHousehold())
                ? new Boost($owner->getHousehold(), BoostKind::Points, $friend, $owner, $now)
                : throw new \InvalidArgumentException('A targeted boost is for another member of the household.'),
            default => throw new \LogicException(\sprintf('A %s is not activated.', $gift->getKind()->value)),
        };

        $gift->use($now);
        $this->entityManager->persist($boost);
        $this->entityManager->flush();
        $this->boosts->reset();

        return $boost;
    }

    /** @param ?Pet $pet null: a treat for every pet */
    public function treat(Gift $gift, ?Pet $pet): void
    {
        $this->assertUsable($gift);
        if (GiftKind::Treat !== $gift->getKind() || (null !== $pet && $pet->getHousehold() !== $gift->getOwner()->getHousehold())) {
            throw new \InvalidArgumentException('Treats are for the pets of the household.');
        }

        $gift->use($this->clock->now(), $pet);
        $this->entityManager->flush();
    }

    public function offer(Gift $gift, Member $friend): void
    {
        $this->assertUsable($gift);
        if (GiftKind::StreakFreeze !== $gift->getKind() || $friend === $gift->getOwner() || !$friend->belongsTo($gift->getOwner()->getHousehold())) {
            throw new \InvalidArgumentException('Only a streak freeze can be offered, to another member of the household.');
        }

        $gift->offerTo($friend);
        $this->entityManager->flush();
    }

    private function assertUsable(Gift $gift): void
    {
        if ($gift->isUsed()) {
            throw new \LogicException('This gift was already used.');
        }
    }
}
