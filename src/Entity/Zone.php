<?php

namespace App\Entity;

use App\Plan\RoomShape;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Zone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'zones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Donne un nom à la zone.')]
    #[Assert\Length(max: 60)]
    private string $name;

    /** A private zone (someone's bedroom or own bathroom) counts for points, not for the Casa's mood. */
    #[ORM\Column]
    private bool $private;

    /** Its outline on the plan of the house, see App\Plan\RoomShape. */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Regex(pattern: RoomShape::PATTERN, message: 'Des points « x,y » séparés par des espaces, au moins trois, et éventuellement « @x,y » pour le nom.')]
    #[Assert\Length(max: 500)]
    private ?string $planShape = null;

    public function __construct(Household $household, string $name, bool $private = false)
    {
        $this->household = $household;
        $this->name = $name;
        $this->private = $private;
        $household->addZone($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): void
    {
        $this->private = $private;
    }

    public function getPlanShape(): ?string
    {
        return $this->planShape;
    }

    public function setPlanShape(?string $planShape): void
    {
        $this->planShape = null !== $planShape && '' !== trim($planShape) ? trim($planShape) : null;
    }
}
