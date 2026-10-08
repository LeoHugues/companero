<?php

namespace App\Presence;

use App\Calendar\Week;
use App\Entity\Member;
use App\Entity\Presence;
use App\Repository\PresenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Keeps track of who is around: days a week (for the goals) and right now (for the reminders). */
final readonly class PresenceRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PresenceRepository $presences,
        private ClockInterface $clock,
    ) {
    }

    /** From this week on, the member is around this many days a week. */
    public function declareDays(Member $member, int $days): void
    {
        $now = $this->clock->now();
        $week = Week::containing($now);

        $presence = $this->presences->findForWeek($member, $week);
        if (null === $presence) {
            $this->entityManager->persist(new Presence($member, $week->start, $days));
        } else {
            $presence->setDays($days);
        }
        if (0 === $days) {
            // Away for the whole week: nobody should count on them today either.
            $member->setAtHome(false, $now);
        }

        $this->entityManager->flush();
    }

    public function setAtHome(Member $member, bool $atHome): void
    {
        $member->setAtHome($atHome, $this->clock->now());
        $this->entityManager->flush();
    }
}
