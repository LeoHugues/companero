<?php

namespace App\Entity;

use App\Enum\TaskCategory;
use App\Repository\CatalogItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A ready-made one-off task (trim the hedge, clean the gutters…) launched by hand when needed. */
#[ORM\Entity(repositoryClass: CatalogItemRepository::class)]
class CatalogItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(enumType: TaskCategory::class)]
    private TaskCategory $category;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $points;

    public function __construct(Household $household, string $title, TaskCategory $category, int $points)
    {
        $this->household = $household;
        $this->title = $title;
        $this->category = $category;
        $this->points = $points;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCategory(): TaskCategory
    {
        return $this->category;
    }

    public function getPoints(): int
    {
        return $this->points;
    }
}
