<?php

namespace App\Entity;

use App\Enum\PointReason;
use App\Repository\PointEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** One line of the points journal: every total (XP, weekly score…) is a sum of these. */
#[ORM\Entity(repositoryClass: PointEntryRepository::class)]
#[ORM\Index(name: 'point_entry_date_idx', columns: ['occurred_at'])]
class PointEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\Column(enumType: PointReason::class)]
    private PointReason $reason;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $points;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Completion $completion;

    public function __construct(Member $member, PointReason $reason, int $points, string $label, \DateTimeImmutable $occurredAt, ?Completion $completion = null)
    {
        $this->member = $member;
        $this->reason = $reason;
        $this->points = $points;
        $this->label = $label;
        $this->occurredAt = $occurredAt;
        $this->completion = $completion;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getReason(): PointReason
    {
        return $this->reason;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getCompletion(): ?Completion
    {
        return $this->completion;
    }
}
