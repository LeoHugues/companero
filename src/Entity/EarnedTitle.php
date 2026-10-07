<?php

namespace App\Entity;

use App\Repository\EarnedTitleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** The fun title a member earned for a closed week ("Pro du rattrapage"…). */
#[ORM\Entity(repositoryClass: EarnedTitleRepository::class)]
#[ORM\UniqueConstraint(name: 'earned_title_week_unique', columns: ['member_id', 'week_start'])]
class EarnedTitle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $weekStart;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 120)]
    private string $reason;

    public function __construct(Member $member, \DateTimeImmutable $weekStart, string $name, string $reason)
    {
        $this->member = $member;
        $this->weekStart = $weekStart;
        $this->name = $name;
        $this->reason = $reason;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
