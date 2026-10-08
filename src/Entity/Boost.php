<?php

namespace App\Entity;

use App\Enum\BoostKind;
use App\Repository\BoostRepository;
use Doctrine\ORM\Mapping as ORM;

/** An activated boost: for a while, tasks earn +1 point (or XP) every 3 points. */
#[ORM\Entity(repositoryClass: BoostRepository::class)]
#[ORM\Index(name: 'boost_period_idx', columns: ['household_id', 'ends_at'])]
class Boost
{
    public const DURATION_HOURS = 24;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    /** Who benefits from it; null: the whole household. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Member $beneficiary;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $grantedBy;

    #[ORM\Column(enumType: BoostKind::class)]
    private BoostKind $kind;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    public function __construct(Household $household, BoostKind $kind, ?Member $beneficiary, ?Member $grantedBy, \DateTimeImmutable $startsAt)
    {
        $this->household = $household;
        $this->kind = $kind;
        $this->beneficiary = $beneficiary;
        $this->grantedBy = $grantedBy;
        $this->startsAt = $startsAt;
        $this->endsAt = $startsAt->modify(\sprintf('+%d hours', self::DURATION_HOURS));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getBeneficiary(): ?Member
    {
        return $this->beneficiary;
    }

    public function getGrantedBy(): ?Member
    {
        return $this->grantedBy;
    }

    public function getKind(): BoostKind
    {
        return $this->kind;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }
}
