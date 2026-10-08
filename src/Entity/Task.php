<?php

namespace App\Entity;

use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Index(name: 'task_active_idx', columns: ['household_id', 'archived_at'])]
class Task
{
    public const DEFAULT_MARGIN_HOURS = 24;
    public const DEFAULT_RESERVATION_HOURS = 24;
    /** scheduledWeekday value for a task that comes back every day (feeding the cats…). */
    public const EVERY_DAY = 0;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Zone $zone = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Qu’est-ce qu’il faut faire ?')]
    #[Assert\Length(max: 120)]
    private string $title = '';

    #[ORM\Column(enumType: TaskKind::class)]
    private TaskKind $kind = TaskKind::OneOff;

    #[ORM\Column(enumType: TaskCategory::class)]
    private TaskCategory $category = TaskCategory::Cleaning;

    #[ORM\Column(type: Types::SMALLINT)]
    /** 10 points ≈ 5 minutes of effort, then adjusted for how much of a chore it is. */
    #[Assert\Range(min: 1, max: 500)]
    private int $points = 20;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 365)]
    private ?int $rhythmDays = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 14)]
    private ?int $weeklyCommitment = null;

    /** ISO-8601 day of the week for scheduled tasks, or EVERY_DAY. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 0, max: 7)]
    private ?int $scheduledWeekday = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledTime = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 168)]
    private int $marginHours = self::DEFAULT_MARGIN_HOURS;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Pet $pet = null;

    /** Who takes care of it. Nobody in particular: everyone at home is reminded. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $assignee = null;

    /** Reminded when the assignee is away. Nobody in particular: everyone at home. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $backup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastCompletedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $lastCompletedBy = null;

    /** Once done, it cannot be done again before this many hours (the dishwasher is not emptied twice in a row). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 8760)]
    private ?int $cooldownHours = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $reservedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reservedUntil = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    public function __construct(Household $household, Member $createdBy, \DateTimeImmutable $createdAt)
    {
        $this->household = $household;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
    }

    #[Assert\Callback]
    public function validateSchedule(ExecutionContextInterface $context): void
    {
        if (TaskKind::Rolling === $this->kind && null === $this->rhythmDays) {
            $context->buildViolation('Tous les combien de jours ?')->atPath('rhythmDays')->addViolation();
        }

        if (TaskKind::Scheduled === $this->kind) {
            if (null === $this->scheduledWeekday) {
                $context->buildViolation('Quel jour ?')->atPath('scheduledWeekday')->addViolation();
            }
            if (null === $this->scheduledTime) {
                $context->buildViolation('À quelle heure ?')->atPath('scheduledTime')->addViolation();
            }
        }
    }

    /** Drops the settings that do not apply to the current kind of task. */
    public function normalizeSchedule(): void
    {
        if (TaskKind::Rolling !== $this->kind) {
            $this->rhythmDays = null;
            $this->weeklyCommitment = null;
        }
        if (TaskKind::Scheduled !== $this->kind) {
            $this->scheduledWeekday = null;
            $this->scheduledTime = null;
        }
        if (TaskKind::OneOff !== $this->kind) {
            $this->dueAt = null;
        }
        if (TaskKind::Quick !== $this->kind) {
            $this->cooldownHours = null;
        }
        if (null === $this->assignee || $this->backup === $this->assignee) {
            $this->backup = null;
        }
    }

    public function complete(\DateTimeImmutable $at, ?Member $by = null): void
    {
        $this->lastCompletedAt = $at;
        $this->lastCompletedBy = $by;
        $this->release();

        if (!$this->kind->isRecurring()) {
            $this->archive($at);
        }
    }

    public function reserveFor(Member $member, \DateTimeImmutable $until): void
    {
        $this->reservedBy = $member;
        $this->reservedUntil = $until;
    }

    public function release(): void
    {
        $this->reservedBy = null;
        $this->reservedUntil = null;
    }

    /** Who promised to do it — an expired reservation frees the task again. */
    public function reservedByAt(\DateTimeImmutable $now): ?Member
    {
        if (null === $this->reservedUntil || $this->reservedUntil <= $now) {
            return null;
        }

        return $this->reservedBy;
    }

    public function archive(\DateTimeImmutable $at): void
    {
        $this->archivedAt = $at;
    }

    /** When it can be done again; null: right now. */
    public function availableAt(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if (null === $this->cooldownHours || null === $this->lastCompletedAt) {
            return null;
        }
        $availableAt = $this->lastCompletedAt->modify(\sprintf('+%d hours', $this->cooldownHours));

        return $availableAt > $now ? $availableAt : null;
    }

    public function isDaily(): bool
    {
        return TaskKind::Scheduled === $this->kind && self::EVERY_DAY === $this->scheduledWeekday;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function isShared(): bool
    {
        return !$this->zone?->isPrivate();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getZone(): ?Zone
    {
        return $this->zone;
    }

    public function setZone(?Zone $zone): void
    {
        $this->zone = $zone;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getKind(): TaskKind
    {
        return $this->kind;
    }

    public function setKind(TaskKind $kind): void
    {
        $this->kind = $kind;
    }

    public function getCategory(): TaskCategory
    {
        return $this->category;
    }

    public function setCategory(TaskCategory $category): void
    {
        $this->category = $category;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getRhythmDays(): ?int
    {
        return $this->rhythmDays;
    }

    public function setRhythmDays(?int $rhythmDays): void
    {
        $this->rhythmDays = $rhythmDays;
    }

    public function getWeeklyCommitment(): ?int
    {
        return $this->weeklyCommitment;
    }

    public function setWeeklyCommitment(?int $weeklyCommitment): void
    {
        $this->weeklyCommitment = $weeklyCommitment;
    }

    public function getScheduledWeekday(): ?int
    {
        return $this->scheduledWeekday;
    }

    public function setScheduledWeekday(?int $scheduledWeekday): void
    {
        $this->scheduledWeekday = $scheduledWeekday;
    }

    public function getScheduledTime(): ?\DateTimeImmutable
    {
        return $this->scheduledTime;
    }

    public function setScheduledTime(?\DateTimeImmutable $scheduledTime): void
    {
        $this->scheduledTime = $scheduledTime;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): void
    {
        $this->dueAt = $dueAt;
    }

    public function getPet(): ?Pet
    {
        return $this->pet;
    }

    public function setPet(?Pet $pet): void
    {
        $this->pet = $pet;
    }

    public function getAssignee(): ?Member
    {
        return $this->assignee;
    }

    public function setAssignee(?Member $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function getBackup(): ?Member
    {
        return $this->backup;
    }

    public function setBackup(?Member $backup): void
    {
        $this->backup = $backup;
    }

    public function getMarginHours(): int
    {
        return $this->marginHours;
    }

    public function setMarginHours(int $marginHours): void
    {
        $this->marginHours = $marginHours;
    }

    public function getCreatedBy(): Member
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastCompletedAt(): ?\DateTimeImmutable
    {
        return $this->lastCompletedAt;
    }

    public function getLastCompletedBy(): ?Member
    {
        return $this->lastCompletedBy;
    }

    public function getCooldownHours(): ?int
    {
        return $this->cooldownHours;
    }

    public function setCooldownHours(?int $cooldownHours): void
    {
        $this->cooldownHours = $cooldownHours;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }
}
