<?php

namespace App\Repository;

use App\Entity\Household;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Task> */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /** @return list<Task> */
    public function findActive(Household $household): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('z', 'r')
            ->leftJoin('t.zone', 'z')
            ->leftJoin('t.reservedBy', 'r')
            ->andWhere('t.household = :household')
            ->andWhere('t.archivedAt IS NULL')
            ->setParameter('household', $household)
            ->orderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Task> */
    public function findWithWeeklyCommitment(Household $household): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.household = :household')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('t.weeklyCommitment IS NOT NULL')
            ->setParameter('household', $household)
            ->orderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
