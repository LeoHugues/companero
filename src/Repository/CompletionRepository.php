<?php

namespace App\Repository;

use App\Entity\Completion;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Task;
use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Completion> */
class CompletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Completion::class);
    }

    /** @return array<int, int> completions count indexed by task id */
    public function countByTask(Household $household, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.task) AS task', 'COUNT(c.id) AS total')
            ->join('c.task', 't')
            ->andWhere('t.household = :household')
            ->andWhere('c.completedAt >= :from AND c.completedAt < :to')
            ->setParameter('household', $household)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('c.task')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => [(int) $row['task'], (int) $row['total']], $rows), 1, 0);
    }

    public function countForTask(Task $task, \DateTimeImmutable $from, \DateTimeImmutable $to, ?Completion $except = null): int
    {
        $query = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.task = :task')
            ->andWhere('c.completedAt >= :from AND c.completedAt < :to')
            ->setParameter('task', $task)
            ->setParameter('from', $from)
            ->setParameter('to', $to);
        if (null !== $except?->getId()) {
            $query->andWhere('c.id != :except')->setParameter('except', $except->getId());
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /** The task's latest completion, or the latest one before a moment, leaving one aside. */
    public function findLatestForTask(Task $task, ?\DateTimeImmutable $before = null, ?Completion $except = null): ?Completion
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.task = :task')
            ->setParameter('task', $task)
            ->orderBy('c.completedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1);
        if (null !== $before) {
            $query->andWhere('c.completedAt < :before')->setParameter('before', $before);
        }
        if (null !== $except?->getId()) {
            $query->andWhere('c.id != :except')->setParameter('except', $except->getId());
        }

        return $query->getQuery()->getOneOrNullResult();
    }

    /** @return list<Completion> */
    public function findForHousehold(Household $household, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('t', 'm')
            ->join('c.task', 't')
            ->join('c.member', 'm')
            ->andWhere('t.household = :household')
            ->andWhere('c.completedAt >= :from AND c.completedAt < :to')
            ->setParameter('household', $household)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Completion> latest first */
    public function findRecentForTask(Task $task, int $limit = 8): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('m')
            ->join('c.member', 'm')
            ->andWhere('c.task = :task')
            ->setParameter('task', $task)
            ->orderBy('c.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Completion> latest first */
    public function findRecentForZone(Zone $zone, \DateTimeImmutable $since, int $limit = 12): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('t', 'm')
            ->join('c.task', 't')
            ->join('c.member', 'm')
            ->andWhere('t.zone = :zone')
            ->andWhere('c.completedAt >= :since')
            ->setParameter('zone', $zone)
            ->setParameter('since', $since)
            ->orderBy('c.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Completion> latest first */
    public function findForMember(Member $member, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('t', 'z')
            ->join('c.task', 't')
            ->leftJoin('t.zone', 'z')
            ->andWhere('c.member = :member')
            ->andWhere('c.completedAt >= :from AND c.completedAt < :to')
            ->setParameter('member', $member)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
