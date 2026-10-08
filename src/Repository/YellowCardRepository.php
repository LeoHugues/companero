<?php

namespace App\Repository;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\YellowCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<YellowCard> */
class YellowCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, YellowCard::class);
    }

    /** @return list<YellowCard> cards the member has not seen yet, oldest first */
    public function findUnseenBy(Member $member): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.givenTo = :member')
            ->andWhere('c.seenAt IS NULL')
            ->setParameter('member', $member)
            ->orderBy('c.givenAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<YellowCard> latest first */
    public function findReceivedBy(Member $member, int $limit = 20): array
    {
        return $this->findBy(['givenTo' => $member], ['givenAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** @return list<YellowCard> latest first */
    public function findGivenBy(Member $member, int $limit = 20): array
    {
        return $this->findBy(['givenBy' => $member], ['givenAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** @return list<YellowCard> the household's cards given between two moments, latest first */
    public function findForHousehold(Household $household, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.givenTo', 'm')
            ->andWhere('m.household = :household')
            ->andWhere('c.givenAt >= :from AND c.givenAt < :to')
            ->setParameter('household', $household)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.givenAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
