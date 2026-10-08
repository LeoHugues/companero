<?php

namespace App\Task;

use App\Entity\Completion;
use App\Entity\Member;
use Symfony\Component\Validator\Constraints as Assert;

/** What can be put right about a completion: who, when, and how many points it was worth. */
final class CompletionChange
{
    public function __construct(
        #[Assert\NotNull(message: 'Qui l’a fait ?')]
        public ?Member $member,
        #[Assert\NotNull(message: 'Quand ?')]
        #[Assert\LessThanOrEqual('now', message: 'Pas dans le futur : ce qui est noté est déjà fait.')]
        public ?\DateTimeImmutable $completedAt,
        #[Assert\NotNull(message: 'Combien de points ?')]
        #[Assert\Range(min: 0, max: 500)]
        public ?int $points,
    ) {
    }

    public static function of(Completion $completion, ?int $basePoints): self
    {
        return new self($completion->getMember(), $completion->getCompletedAt(), $basePoints ?? $completion->getTask()->getPoints());
    }
}
