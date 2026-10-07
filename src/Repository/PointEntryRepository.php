<?php

namespace App\Repository;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Enum\PointReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PointEntry> */
class PointEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PointEntry::class);
    }

    public function totalFor(Member $member): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.points), 0)')
            ->andWhere('p.member = :member')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> points indexed by member id */
    public function sumByMember(Household $household, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.member) AS member', 'SUM(p.points) AS total')
            ->join('p.member', 'm')
            ->andWhere('m.household = :household')
            ->andWhere('p.occurredAt >= :from AND p.occurredAt < :to')
            ->setParameter('household', $household)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('p.member')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => [(int) $row['member'], (int) $row['total']], $rows), 1, 0);
    }

    public function hasEntry(Member $member, PointReason $reason, \DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        return (bool) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.member = :member')
            ->andWhere('p.reason = :reason')
            ->andWhere('p.occurredAt >= :from AND p.occurredAt < :to')
            ->setParameter('member', $member)
            ->setParameter('reason', $reason)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
