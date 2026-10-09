<?php

namespace App\Entity;

use App\Enum\PetSpecies;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * Its humans: they are reminded of its tasks first; when none of them is home, the others
     * at home are, standing in for them.
     *
     * @var Collection<int, Member>
     */
    #[ORM\ManyToMany(targetEntity: Member::class)]
    #[ORM\JoinTable(name: 'pet_owner')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $owners;

    public function __construct(Household $household, string $name, PetSpecies $species = PetSpecies::Cat, ?string $description = null)
    {
        $this->household = $household;
        $this->name = $name;
        $this->species = $species;
        $this->description = $description;
        $this->owners = new ArrayCollection();
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

    /** @return Collection<int, Member> */
    public function getOwners(): Collection
    {
        return $this->owners;
    }

    public function addOwner(Member $owner): void
    {
        if (!$this->owners->contains($owner)) {
            $this->owners->add($owner);
        }
    }

    public function removeOwner(Member $owner): void
    {
        $this->owners->removeElement($owner);
    }
}
