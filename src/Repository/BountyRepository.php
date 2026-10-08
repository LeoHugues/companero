<?php

namespace App\Repository;

use App\Entity\Bounty;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Bounty> */
class BountyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bounty::class);
    }

    /** @return array<int, Bounty> the surprises of a week, indexed by task id */
    public function findForWeek(Household $household, \DateTimeImmutable $weekStart): array
    {
        $bounties = $this->createQueryBuilder('b')
            ->addSelect('t')
            ->join('b.task', 't')
            ->andWhere('t.household = :household')
            ->andWhere('b.weekStart = :week')
            ->setParameter('household', $household)
            ->setParameter('week', $weekStart->setTime(0, 0), 'date_immutable')
            ->getQuery()
            ->getResult();

        $byTask = [];
        foreach ($bounties as $bounty) {
            $byTask[$bounty->getTask()->getId()] = $bounty;
        }

        return $byTask;
    }

    public function findOneForWeek(Task $task, \DateTimeImmutable $weekStart): ?Bounty
    {
        return $this->findOneBy(['task' => $task, 'weekStart' => $weekStart->setTime(0, 0)]);
    }

    /** @return list<Bounty> the surprises a member found, latest first */
    public function findClaimedBy(Member $member, int $limit = 30): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('t')
            ->join('b.task', 't')
            ->andWhere('b.claimedBy = :member')
            ->setParameter('member', $member)
            ->orderBy('b.claimedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countClaimedBy(Member $member): int
    {
        return $this->count(['claimedBy' => $member]);
    }
}
