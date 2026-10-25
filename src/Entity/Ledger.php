<?php

namespace App\Entity;

use App\Ledger\Currency;
use App\Repository\LedgerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An account kept together, like a tricount: the coloc's common expenses, or a weekend away with a friend. Its
 * participants are colocs or guests known by their name; everything is counted in its currency.
 */
#[ORM\Entity(repositoryClass: LedgerRepository::class)]
class Ledger
{
    public const NAME_MAX_LENGTH = 60;
    public const DESCRIPTION_MAX_LENGTH = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    #[Assert\NotBlank(message: 'Donne un nom à ce compte.')]
    #[Assert\Length(max: self::NAME_MAX_LENGTH)]
    private string $name;

    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    #[Assert\Length(max: self::DESCRIPTION_MAX_LENGTH)]
    private ?string $description = null;

    #[ORM\Column(length: 3)]
    #[Assert\Choice(callback: [Currency::class, 'codes'])]
    private string $currency = Currency::DEFAULT;

    /** The whole coloc: a member who joins the household joins this account too. */
    #[ORM\Column(options: ['default' => false])]
    private bool $everyone = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Participant> */
    #[ORM\OneToMany(targetEntity: Participant::class, mappedBy: 'ledger', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $participants;

    public function __construct(Household $household, string $name, \DateTimeImmutable $createdAt)
    {
        $this->household = $household;
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $description = trim((string) $description);
        $this->description = '' === $description ? null : $description;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function isEveryone(): bool
    {
        return $this->everyone;
    }

    public function setEveryone(bool $everyone): void
    {
        $this->everyone = $everyone;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Participant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /** @return array<int, Participant> by id */
    public function participantsById(): array
    {
        $byId = [];
        foreach ($this->participants as $participant) {
            $byId[(int) $participant->getId()] = $participant;
        }

        return $byId;
    }

    public function participantFor(Member $member): ?Participant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getMember() === $member) {
                return $participant;
            }
        }

        return null;
    }

    /** A coloc, or a guest known by their name only. */
    public function addParticipant(string $name, ?Member $member = null): Participant
    {
        $position = max([-1, ...$this->participants->map(static fn (Participant $participant): int => $participant->getPosition())->toArray()]) + 1;
        $participant = new Participant($this, $name, $member, $position);
        $this->participants->add($participant);

        return $participant;
    }

    public function removeParticipant(Participant $participant): void
    {
        $this->participants->removeElement($participant);
    }

    /**
     * The colocs who are not in it yet, added (the whole coloc's account welcomes the newcomers).
     *
     * @param iterable<Member> $members
     *
     * @return bool true if someone was added
     */
    public function welcome(iterable $members): bool
    {
        $added = false;
        foreach ($members as $member) {
            if (null === $this->participantFor($member)) {
                $this->addParticipant($member->getName(), $member);
                $added = true;
            }
        }

        return $added;
    }
}
