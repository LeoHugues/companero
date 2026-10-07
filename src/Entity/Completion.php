<?php

namespace App\Entity;

use App\Enum\Urgency;
use App\Repository\CompletionRepository;
use Doctrine\ORM\Mapping as ORM;

/** "Member M did task T at that moment." The urgency is kept as it was when the task was done. */
#[ORM\Entity(repositoryClass: CompletionRepository::class)]
#[ORM\Index(name: 'completion_date_idx', columns: ['completed_at'])]
class Completion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    #[ORM\Column(enumType: Urgency::class)]
    private Urgency $urgency;

    public function __construct(Task $task, Member $member, \DateTimeImmutable $completedAt, Urgency $urgency)
    {
        $this->task = $task;
        $this->member = $member;
        $this->completedAt = $completedAt;
        $this->urgency = $urgency;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getUrgency(): Urgency
    {
        return $this->urgency;
    }
}
