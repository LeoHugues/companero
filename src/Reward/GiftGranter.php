<?php

namespace App\Reward;

use App\Entity\Gift;
use App\Entity\Member;
use App\Progress\LevelProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Hands out the gifts of every level a member reached and has not been rewarded for yet. */
final readonly class GiftGranter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LevelProvider $levels,
        private LevelGifts $levelGifts,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<Gift> the gifts just earned (flushed) */
    public function catchUp(Member $member, ?\DateTimeImmutable $at = null): array
    {
        $level = $this->levels->levelOf($member)->number;
        $hasPets = !$member->getHousehold()->getPets()->isEmpty();
        $at ??= $this->clock->now();

        $gifts = [];
        for ($reached = $member->getGiftedLevel() + 1; $reached <= $level; ++$reached) {
            foreach ($this->levelGifts->forLevel($reached, $hasPets) as $kind) {
                $gifts[] = $gift = new Gift($member, $kind, \sprintf('Niveau %d', $reached), $at);
                $this->entityManager->persist($gift);
            }
        }

        if ($level > $member->getGiftedLevel()) {
            $member->setGiftedLevel($level);
            $this->entityManager->flush();
        }

        return $gifts;
    }
}
