<?php

namespace App\Repository;

use App\Calendar\Week;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Presence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Presence> */
class PresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Presence::class);
    }

    /** Days a member is around during a week: the latest declaration made up to that week, or the whole week. */
    public function daysOf(Member $member, Week $week): int
    {
        return $this->daysByMember($member->getHousehold(), $week)[$member->getId()] ?? Presence::FULL_WEEK;
    }

    /** @return array<int, int> days of presence indexed by member id (members who never declared anything are absent from it) */
    public function daysByMember(Household $household, Week $week): array
    {
        /** @var list<Presence> $presences */
        $presences = $this->createQueryBuilder('p')
            ->join('p.member', 'm')
            ->andWhere('m.household = :household')
            ->andWhere('p.weekStart <= :start')
            ->setParameter('household', $household)
            ->setParameter('start', $week->start, Types::DATE_IMMUTABLE)
            ->orderBy('p.weekStart', 'ASC')
            ->getQuery()
            ->getResult();

        $days = [];
        foreach ($presences as $presence) {
            $days[(int) $presence->getMember()->getId()] = $presence->getDays();
        }

        return $days;
    }

    public function findForWeek(Member $member, Week $week): ?Presence
    {
        return $this->findOneBy(['member' => $member, 'weekStart' => $week->start]);
    }
}
