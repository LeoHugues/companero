<?php

namespace App\Household;

use App\Entity\CatalogItem;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Zone;
use App\Enum\TaskCategory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Creates a household with sensible defaults (zones, catalogue) and its first member. */
final readonly class HouseholdFounder
{
    private const ZONES = ['Cuisine', 'Salon', 'Salle de bain', 'WC', 'Entrée'];

    private const CATALOG = [
        ['Tailler la haie', TaskCategory::Garden, 100],
        ['Tondre la pelouse', TaskCategory::Garden, 60],
        ['Nettoyer les gouttières', TaskCategory::Repair, 120],
        ['Dégivrer le congélateur', TaskCategory::Cleaning, 60],
        ['Nettoyer les vitres', TaskCategory::Cleaning, 50],
        ['Ranger et nettoyer un placard', TaskCategory::Cleaning, 60],
        ['Petite réparation', TaskCategory::Repair, 20],
        ['Racheter du papier toilette', TaskCategory::Shopping, 10],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberRegistrar $registrar,
        private ClockInterface $clock,
    ) {
    }

    public function found(Founding $founding): Member
    {
        $household = new Household(trim($founding->householdName), $this->clock->now());
        $household->setCleaningDay($founding->cleaningDay);

        foreach (self::ZONES as $name) {
            new Zone($household, $name);
        }
        foreach (self::CATALOG as [$title, $category, $points]) {
            $this->entityManager->persist(new CatalogItem($household, $title, $category, $points));
        }
        $this->entityManager->persist($household);

        return $this->registrar->register($household, $founding);
    }
}
