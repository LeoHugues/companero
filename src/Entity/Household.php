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
    public const DEFAULT_WEEKLY_GOAL = 250;

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

    /** The household's own weekly goal, shared by everyone: reaching it earns the team bonus. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => self::DEFAULT_WEEKLY_GOAL])]
    #[Assert\Range(min: 10, max: 5000)]
    private int $weeklyGoal = self::DEFAULT_WEEKLY_GOAL;

    /** Weeks in a row with the household goal reached. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $streak = 0;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $bestStreak = 0;

    /** The last week the streak was counted for, so that closing a week twice changes nothing. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $streakCountedUntil = null;

    /** The last week whose surprises were drawn, so that a week is planned only once. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $bountiesPlannedFor = null;

    /** On the cleaning day, everyone's tasks earn +1 point every 3 points. */
    #[ORM\Column(options: ['default' => true])]
    private bool $cleaningDayBoost = true;

    #[ORM\Column(length: 32, unique: true)]
    private string $inviteToken;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** When a rule of the charter was last written, moved or removed: everyone is asked to read it again. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $charterUpdatedAt = null;

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

    /** @var Collection<int, CharterRule> */
    #[ORM\OneToMany(targetEntity: CharterRule::class, mappedBy: 'household', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $charterRules;

    public function __construct(string $name, \DateTimeImmutable $createdAt)
    {
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->members = new ArrayCollection();
        $this->zones = new ArrayCollection();
        $this->pets = new ArrayCollection();
        $this->charterRules = new ArrayCollection();
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

    public function getWeeklyGoal(): int
    {
        return $this->weeklyGoal;
    }

    public function setWeeklyGoal(int $weeklyGoal): void
    {
        $this->weeklyGoal = $weeklyGoal;
    }

    public function getStreak(): int
    {
        return $this->streak;
    }

    public function getBestStreak(): int
    {
        return $this->bestStreak;
    }

    /** Counts a finished week once: extends the streak if the household goal was reached, ends it otherwise. */
    public function countWeek(\DateTimeImmutable $weekStart, bool $goalReached): void
    {
        if (null !== $this->streakCountedUntil && $weekStart <= $this->streakCountedUntil) {
            return;
        }
        $this->streakCountedUntil = $weekStart;
        $this->streak = $goalReached ? $this->streak + 1 : 0;
        $this->bestStreak = max($this->bestStreak, $this->streak);
    }

    /** True the first time a week is planned: its surprises are to be drawn. */
    public function planBounties(\DateTimeImmutable $weekStart): bool
    {
        $weekStart = $weekStart->setTime(0, 0);
        if (null !== $this->bountiesPlannedFor && $weekStart <= $this->bountiesPlannedFor) {
            return false;
        }
        $this->bountiesPlannedFor = $weekStart;

        return true;
    }

    public function hasCleaningDayBoost(): bool
    {
        return $this->cleaningDayBoost;
    }

    public function setCleaningDayBoost(bool $cleaningDayBoost): void
    {
        $this->cleaningDayBoost = $cleaningDayBoost;
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

    public function removeMember(Member $member): void
    {
        $this->members->removeElement($member);
    }

    /** @return list<Member> the profiles waiting for their coloc to claim them */
    public function getUnclaimedMembers(): array
    {
        return array_values(array_filter($this->members->toArray(), static fn (Member $member): bool => !$member->isClaimed()));
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

    /** @return Collection<int, CharterRule> */
    public function getCharterRules(): Collection
    {
        return $this->charterRules;
    }

    public function addCharterRule(CharterRule $rule): void
    {
        if (!$this->charterRules->contains($rule)) {
            $rule->setPosition(\count($this->charterRules));
            $this->charterRules->add($rule);
        }
    }

    public function removeCharterRule(CharterRule $rule): void
    {
        $this->charterRules->removeElement($rule);
        $this->renumberCharter(array_values($this->charterRules->toArray()));
    }

    /** One step up (-1) or down (+1) in the charter. */
    public function moveCharterRule(CharterRule $rule, int $offset): void
    {
        $rules = array_values($this->charterRules->toArray());
        usort($rules, static fn (CharterRule $a, CharterRule $b): int => $a->getPosition() <=> $b->getPosition());
        $from = array_search($rule, $rules, true);
        $to = false === $from ? -1 : $from + $offset;
        if ($to < 0 || $to >= \count($rules)) {
            return;
        }
        [$rules[$from], $rules[$to]] = [$rules[$to], $rules[$from]];
        $this->renumberCharter($rules);
    }

    public function getCharterUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->charterUpdatedAt;
    }

    /** The charter changed: whoever changed it agrees to it, the others are asked to read it again. */
    public function touchCharter(Member $by, \DateTimeImmutable $at): void
    {
        $this->charterUpdatedAt = $at;
        $by->acceptCharter($at);
    }

    /** @param list<CharterRule> $rules */
    private function renumberCharter(array $rules): void
    {
        foreach ($rules as $position => $rule) {
            $rule->setPosition($position);
        }
    }
}
