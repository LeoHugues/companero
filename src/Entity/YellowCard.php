<?php

namespace App\Entity;

use App\Repository\YellowCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A yellow card given to a coloc about something in particular ("la vaisselle qui traîne").
 * Earned by doing things; symbolic for now: no points lost. The rules (two in a day make a red one…) will come later.
 */
#[ORM\Entity(repositoryClass: YellowCardRepository::class)]
class YellowCard
{
    public const REASON_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $givenBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $givenTo;

    #[ORM\Column(length: self::REASON_MAX_LENGTH)]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::REASON_MAX_LENGTH)]
    private string $reason;

    #[ORM\Column]
    private \DateTimeImmutable $givenAt;

    /** When its receiver first saw it: it is shown once, in big. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $seenAt = null;

    public function __construct(Member $givenBy, Member $givenTo, string $reason, \DateTimeImmutable $givenAt)
    {
        $this->givenBy = $givenBy;
        $this->givenTo = $givenTo;
        $this->reason = $reason;
        $this->givenAt = $givenAt;
    }

    public function markSeen(\DateTimeImmutable $at): void
    {
        $this->seenAt ??= $at;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGivenBy(): Member
    {
        return $this->givenBy;
    }

    public function getGivenTo(): Member
    {
        return $this->givenTo;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getGivenAt(): \DateTimeImmutable
    {
        return $this->givenAt;
    }

    public function getSeenAt(): ?\DateTimeImmutable
    {
        return $this->seenAt;
    }
}
