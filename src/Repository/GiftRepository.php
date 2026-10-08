<?php

namespace App\Repository;

use App\Entity\Gift;
use App\Entity\Household;
use App\Entity\Member;
use App\Enum\GiftKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Gift> */
class GiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gift::class);
    }

    /** @return list<Gift> unused gifts, oldest first */
    public function findUnused(Member $member, ?GiftKind $kind = null): array
    {
        $query = $this->createQueryBuilder('g')
            ->andWhere('g.owner = :member')
            ->andWhere('g.usedAt IS NULL')
            ->setParameter('member', $member)
            ->orderBy('g.earnedAt', 'ASC')
            ->addOrderBy('g.id', 'ASC');
        if (null !== $kind) {
            $query->andWhere('g.kind = :kind')->setParameter('kind', $kind);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<Gift> treats given to the household's pets, latest first */
    public function findTreatsGiven(Household $household, int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('o')
            ->join('g.owner', 'o')
            ->andWhere('o.household = :household')
            ->andWhere('g.kind = :treat')
            ->andWhere('g.usedAt IS NOT NULL')
            ->setParameter('household', $household)
            ->setParameter('treat', GiftKind::Treat)
            ->orderBy('g.usedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
