<?php

namespace App\Entity;

use App\Enum\Rarity;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    public const NOTE_MAX_LENGTH = 255;
    /** The ± buttons of a task to do: a few minutes more or less than usual. */
    public const POINT_STEPS = [5, 10, 15];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    /**
     * Where it is done: one room, or several (the vacuum of the living room goes through the open
     * kitchen and its toilets too); none, the whole house. It counts for each of them on the plan.
     *
     * @var Collection<int, Zone>
     */
    #[ORM\ManyToMany(targetEntity: Zone::class)]
    #[ORM\JoinTable(name: 'task_zone')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $zones;

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

    /** Once the moment has come, how long it may wait before being late (its card turns red). */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 720)]
    private int $marginHours = self::DEFAULT_MARGIN_HOURS;

    /** How long before the moment its card turns orange. Null: the default (see TaskStatusResolver). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 8760)]
    private ?int $warningHours = null;

    #[ORM\Column(enumType: Rarity::class, options: ['default' => 'common'])]
    private Rarity $rarity = Rarity::Common;

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

    /** An occasional task that is needed right now: since when (null: it sleeps). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $raisedAt = null;

    /**
     * A word about this time only ("the bins are in the garage"), not about the template:
     * it goes away once the task is done.
     */
    #[ORM\Column(length: self::NOTE_MAX_LENGTH, nullable: true)]
    #[Assert\Length(max: self::NOTE_MAX_LENGTH)]
    private ?string $note = null;

    /** An express task's place in "En un geste" on the home page (null: after the others, by title). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $quickPosition = null;

    /** An express task left out of "En un geste" on the home page: still in the templates. */
    #[ORM\Column(options: ['default' => false])]
    private bool $quickHidden = false;

    /** More (or fewer) points than the template this time: it was more work, or less. Reset once done. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $pointsAdjustment = 0;

    public function __construct(Household $household, Member $createdBy, \DateTimeImmutable $createdAt)
    {
        $this->household = $household;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->zones = new ArrayCollection();
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
        if (TaskKind::Occasional !== $this->kind) {
            $this->raisedAt = null;
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
        // Done: an occasional task goes back to sleep until it is needed again.
        if (null !== $this->raisedAt && $at >= $this->raisedAt) {
            $this->raisedAt = null;
        }
        // The note and the extra points were about this time: the next one starts afresh.
        if ($this->kind->isRecurring()) {
            $this->note = null;
            $this->pointsAdjustment = 0;
        }

        if (!$this->kind->isRecurring()) {
            $this->archive($at);
        }
    }

    /** After a completion was moved: the latest one left, if any, is the reference again. */
    public function restoreLastCompletion(?\DateTimeImmutable $at, ?Member $by): void
    {
        // A one-off task is archived when it is done: it follows the completion.
        if (!$this->kind->isRecurring() && null !== $at && null !== $this->archivedAt && $this->archivedAt == $this->lastCompletedAt) {
            $this->archivedAt = $at;
        }
        $this->lastCompletedAt = $at;
        $this->lastCompletedBy = $by;
    }

    /** A task noted once it is already done existed at least since then. */
    public function backdateCreation(\DateTimeImmutable $at): void
    {
        if ($at < $this->createdAt) {
            $this->createdAt = $at;
        }
    }

    /** "Ça arrive": an occasional task is needed now, its card shows up until someone does it. */
    public function raise(\DateTimeImmutable $at): void
    {
        if (TaskKind::Occasional === $this->kind && null === $this->raisedAt) {
            $this->raisedAt = $at;
        }
    }

    public function isRaised(): bool
    {
        return null !== $this->raisedAt;
    }

    public function getRaisedAt(): ?\DateTimeImmutable
    {
        return $this->raisedAt;
    }

    /** Counts for the Casa's cleanliness: it comes back on its own, or it is needed right now. */
    public function isExpected(): bool
    {
        return $this->kind->isPlanned() || $this->isRaised();
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

    /** Not in someone's own room. */
    public function isShared(): bool
    {
        return !$this->zones->exists(static fn (int $key, Zone $zone): bool => $zone->isPrivate());
    }

    public function isIn(Zone $zone): bool
    {
        return $this->zones->contains($zone);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
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

    /** Just this room (or the whole house). */
    public function setZone(?Zone $zone): void
    {
        $this->zones->clear();
        if (null !== $zone) {
            $this->zones->add($zone);
        }
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

    /** What doing it this time is worth: the template's points, give or take this time's adjustment. */
    public function getCurrentPoints(): int
    {
        return max(0, $this->points + $this->pointsAdjustment);
    }

    public function getPointsAdjustment(): int
    {
        return $this->pointsAdjustment;
    }

    /** A few points more, or fewer, this time only: never below nothing, never above the maximum. */
    public function adjustPoints(int $delta): void
    {
        $current = max(0, min(500, $this->getCurrentPoints() + $delta));
        $this->pointsAdjustment = $current - $this->points;
    }

    public function getQuickPosition(): ?int
    {
        return $this->quickPosition;
    }

    public function setQuickPosition(?int $quickPosition): void
    {
        $this->quickPosition = $quickPosition;
    }

    public function isQuickHidden(): bool
    {
        return $this->quickHidden;
    }

    public function setQuickHidden(bool $quickHidden): void
    {
        $this->quickHidden = $quickHidden;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $note = null !== $note ? trim($note) : null;
        $this->note = '' === $note ? null : $note;
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

    /** Left empty in the form: the default margin. */
    public function setMarginHours(?int $marginHours): void
    {
        $this->marginHours = $marginHours ?? self::DEFAULT_MARGIN_HOURS;
    }

    public function getWarningHours(): ?int
    {
        return $this->warningHours;
    }

    public function setWarningHours(?int $warningHours): void
    {
        $this->warningHours = $warningHours;
    }

    public function getRarity(): Rarity
    {
        return $this->rarity;
    }

    public function setRarity(Rarity $rarity): void
    {
        $this->rarity = $rarity;
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
