<?php

namespace App\Entity;

use App\Repository\PresenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * "From this week on, I am here N days a week." It stays true for the following weeks
 * until the member moves the slider again, so past weeks keep the goal they had.
 */
#[ORM\Entity(repositoryClass: PresenceRepository::class)]
#[ORM\UniqueConstraint(name: 'presence_week_unique', columns: ['member_id', 'week_start'])]
class Presence
{
    public const FULL_WEEK = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $weekStart;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $days;

    public function __construct(Member $member, \DateTimeImmutable $weekStart, int $days)
    {
        $this->member = $member;
        $this->weekStart = $weekStart;
        $this->setDays($days);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return $this->weekStart;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    public function setDays(int $days): void
    {
        $this->days = max(0, min(self::FULL_WEEK, $days));
    }
}
