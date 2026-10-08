<?php

namespace App\Entity;

use App\Enum\GiftKind;
use App\Repository\GiftRepository;
use Doctrine\ORM\Mapping as ORM;

/** A gift in a member's inventory, earned by levelling up (or offered by a coloc). */
#[ORM\Entity(repositoryClass: GiftRepository::class)]
class Gift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $owner;

    #[ORM\Column(enumType: GiftKind::class)]
    private GiftKind $kind;

    #[ORM\Column(length: 80)]
    private string $reason;

    #[ORM\Column]
    private \DateTimeImmutable $earnedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $offeredBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    /** The pet who got the treat; null with a used treat: all of them. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Pet $pet = null;

    public function __construct(Member $owner, GiftKind $kind, string $reason, \DateTimeImmutable $earnedAt)
    {
        $this->owner = $owner;
        $this->kind = $kind;
        $this->reason = $reason;
        $this->earnedAt = $earnedAt;
    }

    public function offerTo(Member $friend): void
    {
        $this->offeredBy = $this->owner;
        $this->owner = $friend;
    }

    public function use(\DateTimeImmutable $at, ?Pet $pet = null): void
    {
        $this->usedAt = $at;
        $this->pet = $pet;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): Member
    {
        return $this->owner;
    }

    public function getKind(): GiftKind
    {
        return $this->kind;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getEarnedAt(): \DateTimeImmutable
    {
        return $this->earnedAt;
    }

    public function getOfferedBy(): ?Member
    {
        return $this->offeredBy;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getPet(): ?Pet
    {
        return $this->pet;
    }
}
