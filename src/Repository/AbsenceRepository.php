<?php

namespace App\Repository;

use App\Entity\Absence;
use App\Entity\Household;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Absence> */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /** @return list<Absence> absences overlapping [$from, $to) */
    public function findOverlapping(Household $household, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.member', 'm')
            ->andWhere('m.household = :household')
            ->andWhere('a.startsOn < :to AND a.endsOn >= :from')
            ->setParameter('household', $household)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Absence> */
    public function findCurrentAndUpcoming(Member $member, \DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.member = :member')
            ->andWhere('a.endsOn >= :today')
            ->setParameter('member', $member)
            ->setParameter('today', $today->setTime(0, 0))
            ->orderBy('a.startsOn', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
