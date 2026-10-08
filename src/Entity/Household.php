<?php

namespace App\Entity;

use App\Repository\HouseholdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HouseholdRepository::class)]
class Household
{
    public const DEFAULT_CLEANING_DAY = 6;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Donne un nom à ta coloc.')]
    #[Assert\Length(max: 80)]
    private string $name;

    /** ISO-8601 day of the week (1 = Monday … 7 = Sunday). */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 7)]
    private int $cleaningDay = self::DEFAULT_CLEANING_DAY;

    #[ORM\Column(length: 32, unique: true)]
    private string $inviteToken;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Member> */
    #[ORM\OneToMany(targetEntity: Member::class, mappedBy: 'household')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $members;

    /** @var Collection<int, Zone> */
    #[ORM\OneToMany(targetEntity: Zone::class, mappedBy: 'household', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $zones;

    /** @var Collection<int, Pet> */
    #[ORM\OneToMany(targetEntity: Pet::class, mappedBy: 'household', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $pets;

    public function __construct(string $name, \DateTimeImmutable $createdAt)
    {
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->members = new ArrayCollection();
        $this->zones = new ArrayCollection();
        $this->pets = new ArrayCollection();
        $this->regenerateInviteToken();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCleaningDay(): int
    {
        return $this->cleaningDay;
    }

    public function setCleaningDay(int $cleaningDay): void
    {
        $this->cleaningDay = $cleaningDay;
    }

    public function isCleaningDay(\DateTimeImmutable $at): bool
    {
        return (int) $at->format('N') === $this->cleaningDay;
    }

    public function getInviteToken(): string
    {
        return $this->inviteToken;
    }

    public function regenerateInviteToken(): void
    {
        $this->inviteToken = bin2hex(random_bytes(16));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Member> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(Member $member): void
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }
    }

    /** @return Collection<int, Zone> */
    public function getZones(): Collection
    {
        return $this->zones;
    }

    public function addZone(Zone $zone): void
    {
        if (!$this->zones->contains($zone)) {
            $this->zones->add($zone);
        }
    }

    public function removeZone(Zone $zone): void
    {
        $this->zones->removeElement($zone);
    }

    /** @return Collection<int, Pet> */
    public function getPets(): Collection
    {
        return $this->pets;
    }

    public function addPet(Pet $pet): void
    {
        if (!$this->pets->contains($pet)) {
            $this->pets->add($pet);
        }
    }

    public function removePet(Pet $pet): void
    {
        $this->pets->removeElement($pet);
    }
}
