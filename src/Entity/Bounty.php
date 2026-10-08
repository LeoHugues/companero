<?php

namespace App\Entity;

use App\Enum\BountyKind;
use App\Repository\BountyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A surprise hidden in a task card for one week, drawn at random when the week is planned:
 * the first member to do the task that week finds it. Until then, the card is one rarity higher.
 */
#[ORM\Entity(repositoryClass: BountyRepository::class)]
#[ORM\UniqueConstraint(name: 'bounty_task_week_unique', columns: ['task_id', 'week_start'])]
class Bounty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $weekStart;

    #[ORM\Column(enumType: BountyKind::class)]
    private BountyKind $kind;

    /** Points or XP; 0 for a gift. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $amount;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $claimedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Completion $completion = null;

    public function __construct(Task $task, \DateTimeImmutable $weekStart, BountyKind $kind, int $amount = 0)
    {
        $this->task = $task;
        $this->weekStart = $weekStart;
        $this->kind = $kind;
        $this->amount = $amount;
    }

    public function claim(Completion $completion): void
    {
        $this->claimedBy = $completion->getMember();
        $this->claimedAt = $completion->getCompletedAt();
        $this->completion = $completion;
    }

    public function isClaimed(): bool
    {
        return null !== $this->claimedAt;
    }

    public function label(): string
    {
        return $this->kind->label($this->amount);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return $this->weekStart;
    }

    public function getKind(): BountyKind
    {
        return $this->kind;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getClaimedBy(): ?Member
    {
        return $this->claimedBy;
    }

    public function getClaimedAt(): ?\DateTimeImmutable
    {
        return $this->claimedAt;
    }
}
