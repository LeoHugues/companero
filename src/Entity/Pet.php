<?php

namespace App\Entity;

use App\Enum\PetSpecies;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** A pet of the household: its own tasks and reminders (food, litter…), and later its treats. */
#[ORM\Entity]
class Pet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: 'Comment s’appelle-t-il ?')]
    #[Assert\Length(max: 40)]
    private string $name;

    #[ORM\Column(enumType: PetSpecies::class)]
    private PetSpecies $species;

    /** "Chat roux, petit, à poils longs": a word for the coloc, and for its future portrait. */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $description = null;

    public function __construct(Household $household, string $name, PetSpecies $species = PetSpecies::Cat, ?string $description = null)
    {
        $this->household = $household;
        $this->name = $name;
        $this->species = $species;
        $this->description = $description;
        $household->addPet($this);
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

    public function getSpecies(): PetSpecies
    {
        return $this->species;
    }

    public function setSpecies(PetSpecies $species): void
    {
        $this->species = $species;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
