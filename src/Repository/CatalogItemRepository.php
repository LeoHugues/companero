<?php

namespace App\Repository;

use App\Entity\CatalogItem;
use App\Entity\Household;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CatalogItem> */
class CatalogItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogItem::class);
    }

    /** @return list<CatalogItem> */
    public function findForHousehold(Household $household): array
    {
        return $this->findBy(['household' => $household], ['title' => 'ASC']);
    }
}
