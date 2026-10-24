<?php

namespace App\Repository;

use App\Entity\Household;
use App\Entity\ShoppingItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShoppingItem> */
class ShoppingItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingItem::class);
    }

    /** @return list<ShoppingItem> everything the household ever wrote down, missing or not */
    public function findForHousehold(Household $household): array
    {
        return $this->findBy(['household' => $household], ['id' => 'ASC']);
    }

    /** Already written down under that name, give or take capitals, accents and plurals. */
    public function findOneByName(Household $household, string $name): ?ShoppingItem
    {
        foreach ($this->findForHousehold($household) as $item) {
            if ($item->isCalled($name)) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<ShoppingItem> what is missing: the urgent ones first, then the latest noticed */
    public function findMissing(Household $household): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.household = :household')
            ->andWhere('i.missingSince IS NOT NULL')
            ->setParameter('household', $household)
            ->orderBy('i.urgent', 'DESC')
            ->addOrderBy('i.missingSince', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
