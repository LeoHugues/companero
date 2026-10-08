<?php

namespace App\Repository;

use App\Entity\Boost;
use App\Entity\Household;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Boost> */
class BoostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Boost::class);
    }

    /** @return list<Boost> */
    public function findActive(Household $household, \DateTimeImmutable $at): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.household = :household')
            ->andWhere('b.startsAt <= :at AND b.endsAt > :at')
            ->setParameter('household', $household)
            ->setParameter('at', $at)
            ->orderBy('b.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
