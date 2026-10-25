<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Someone an account is kept with: a coloc (their name and face follow their profile), or a guest known by their
 * name only (a friend on the weekend away).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ledger_participant')]
class Participant
{
    public const NAME_MAX_LENGTH = 40;
    /** A guest's face: the sand of the house, ink on it. */
    public const GUEST_COLOR = '#E4D6C3';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ledger $ledger;

    /** Kept for a guest; for a coloc, their name when they were added (their profile's is shown). */
    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    #[Assert\NotBlank(message: 'Comment s’appelle cette personne ?')]
    #[Assert\Length(max: self::NAME_MAX_LENGTH)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $member;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position;

    public function __construct(Ledger $ledger, string $name, ?Member $member, int $position)
    {
        $this->ledger = $ledger;
        $this->name = trim($name);
        $this->member = $member;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLedger(): Ledger
    {
        return $this->ledger;
    }

    public function getName(): string
    {
        return $this->member?->getName() ?? $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function isGuest(): bool
    {
        return null === $this->member;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** Its face, like a coloc's: an initial on a colour. */
    public function getInitial(): string
    {
        return mb_strtoupper(mb_substr($this->getName(), 0, 1));
    }

    public function getColor(): string
    {
        return $this->member?->getColor() ?? self::GUEST_COLOR;
    }
}
