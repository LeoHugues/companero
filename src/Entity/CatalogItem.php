<?php

namespace App\Entity;

use App\Enum\TaskCategory;
use App\Repository\CatalogItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A ready-made one-off task (trim the hedge, clean the gutters…) launched by hand when needed.
 * A new task to do joins the catalogue, so that next time it is picked rather than typed again.
 */
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Zone $zone = null;

    public function __construct(Household $household, string $title, TaskCategory $category, int $points, ?Zone $zone = null)
    {
        $this->household = $household;
        $this->title = $title;
        $this->category = $category;
        $this->points = $points;
        $this->zone = $zone;
    }

    /** What the task to do it starts from: its title, its kind of work, its points, its room. */
    public function fill(Task $task): void
    {
        $task->setTitle($this->title);
        $task->setCategory($this->category);
        $task->setPoints($this->points);
        $task->setZone($this->zone);
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

    public function getZone(): ?Zone
    {
        return $this->zone;
    }
}
