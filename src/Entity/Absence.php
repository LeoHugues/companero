<?php

namespace App\Entity;

use App\Repository\AbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Un petit mot pour la coloc ?')]
    #[Assert\Length(max: 60)]
    private string $label = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Quand pars-tu ?')]
    private ?\DateTimeImmutable $startsOn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Quand reviens-tu ?')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startsOn', message: 'Le retour doit être après le départ.')]
    private ?\DateTimeImmutable $endsOn = null;

    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    /** Number of days of this absence that fall within [$from, $to). */
    public function daysWithin(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        if (null === $this->startsOn || null === $this->endsOn) {
            return 0;
        }

        $start = max($this->startsOn->setTime(0, 0), $from);
        $end = min($this->endsOn->setTime(0, 0)->modify('+1 day'), $to);

        return $start < $end ? (int) $start->diff($end)->days : 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getStartsOn(): ?\DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function setStartsOn(?\DateTimeImmutable $startsOn): void
    {
        $this->startsOn = $startsOn;
    }

    public function getEndsOn(): ?\DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function setEndsOn(?\DateTimeImmutable $endsOn): void
    {
        $this->endsOn = $endsOn;
    }
}
