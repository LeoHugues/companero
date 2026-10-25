<?php

namespace App\Repository;

use App\Entity\Household;
use App\Entity\Ledger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Ledger> */
class LedgerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ledger::class);
    }

    /** @return list<Ledger> the whole coloc's accounts first, then the others as they were opened */
    public function findForHousehold(Household $household): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('p')
            ->leftJoin('l.participants', 'p')
            ->where('l.household = :household')
            ->setParameter('household', $household)
            ->orderBy('l.everyone', 'DESC')
            ->addOrderBy('l.id', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
