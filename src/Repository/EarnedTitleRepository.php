<?php

namespace App\Repository;

use App\Entity\EarnedTitle;
use App\Entity\Household;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EarnedTitle> */
class EarnedTitleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EarnedTitle::class);
    }

    /** @return list<EarnedTitle> */
    public function findForWeek(Household $household, \DateTimeImmutable $weekStart): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('m')
            ->join('e.member', 'm')
            ->andWhere('m.household = :household')
            ->andWhere('e.weekStart = :week')
            ->setParameter('household', $household)
            ->setParameter('week', $weekStart, 'date_immutable')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<EarnedTitle> */
    public function findForMember(Member $member): array
    {
        return $this->findBy(['member' => $member], ['weekStart' => 'DESC']);
    }
}
