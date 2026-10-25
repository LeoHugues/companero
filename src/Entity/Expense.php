<?php

namespace App\Entity;

use App\Enum\ExpenseCategory;
use App\Enum\ExpenseKind;
use App\Enum\SplitMode;
use App\Repository\ExpenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A line of an account: what someone paid for several people (the groceries), received for several people (the
 * deposit given back), or handed to another (a reimbursement). Its shares say who it was for, and how much each.
 *
 * Amounts are in cents: as paid, in its currency, and in the account's currency (the same, unless it was paid
 * abroad: then at the exchange rate typed in).
 */
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
class Expense
{
    public const TITLE_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ledger $ledger;

    #[ORM\Column(length: 10, enumType: ExpenseKind::class)]
    private ExpenseKind $kind;

    #[ORM\Column(length: self::TITLE_MAX_LENGTH)]
    private string $title = '';

    /** As paid, in cents of its currency. */
    #[ORM\Column]
    private int $amount = 0;

    #[ORM\Column(length: 3)]
    private string $currency;

    /** How much one unit of its currency is worth in the account's; null in the account's currency. */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 6, nullable: true)]
    private ?string $exchangeRate = null;

    /** In cents of the account's currency: what the balances count. */
    #[ORM\Column]
    private int $convertedAmount = 0;

    /** Who paid; for an income, who received the money; for a transfer, who handed it. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Participant $paidBy;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $spentOn;

    /** None for a transfer. */
    #[ORM\Column(length: 20, nullable: true, enumType: ExpenseCategory::class)]
    private ?ExpenseCategory $category = null;

    #[ORM\Column(length: 10, enumType: SplitMode::class)]
    private SplitMode $splitMode = SplitMode::Equal;

    /** @var Collection<int, ExpenseShare> */
    #[ORM\OneToMany(targetEntity: ExpenseShare::class, mappedBy: 'expense', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $shares;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $updatedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(Ledger $ledger, ExpenseKind $kind, Participant $paidBy, ?Member $createdBy, \DateTimeImmutable $createdAt)
    {
        $this->ledger = $ledger;
        $this->kind = $kind;
        $this->paidBy = $paidBy;
        $this->currency = $ledger->getCurrency();
        $this->spentOn = $createdAt->setTime(0, 0);
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->shares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLedger(): Ledger
    {
        return $this->ledger;
    }

    public function getHousehold(): Household
    {
        return $this->ledger->getHousehold();
    }

    public function getKind(): ExpenseKind
    {
        return $this->kind;
    }

    public function setKind(ExpenseKind $kind): void
    {
        $this->kind = $kind;
    }

    public function isTransfer(): bool
    {
        return ExpenseKind::Transfer === $this->kind;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = trim((string) preg_replace('/\s+/u', ' ', $title));
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isForeign(): bool
    {
        return $this->currency !== $this->ledger->getCurrency();
    }

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function getConvertedAmount(): int
    {
        return $this->convertedAmount;
    }

    /**
     * What was paid, in its currency, and at what rate; the amount in the account's currency follows.
     *
     * @param string|null $exchangeRate a decimal, null in the account's currency
     */
    public function setAmount(int $amount, string $currency, ?string $exchangeRate, int $convertedAmount): void
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->exchangeRate = $exchangeRate;
        $this->convertedAmount = $convertedAmount;
    }

    public function getPaidBy(): Participant
    {
        return $this->paidBy;
    }

    public function setPaidBy(Participant $paidBy): void
    {
        $this->paidBy = $paidBy;
    }

    public function getSpentOn(): \DateTimeImmutable
    {
        return $this->spentOn;
    }

    public function setSpentOn(\DateTimeImmutable $spentOn): void
    {
        $this->spentOn = $spentOn->setTime(0, 0);
    }

    public function getCategory(): ?ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(?ExpenseCategory $category): void
    {
        $this->category = $category;
    }

    public function getSplitMode(): SplitMode
    {
        return $this->splitMode;
    }

    /** @return Collection<int, ExpenseShare> */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function shareOf(Participant $participant): ?ExpenseShare
    {
        foreach ($this->shares as $share) {
            if ($share->getParticipant() === $participant) {
                return $share;
            }
        }

        return null;
    }

    /** For a transfer, who received the money. */
    public function getRecipient(): ?Participant
    {
        $share = $this->isTransfer() ? $this->shares->first() : null;

        return $share ? $share->getParticipant() : null;
    }

    /**
     * Who it was for, and how much each: what was there before is replaced.
     *
     * @param list<array{participant: Participant, weight: int, amount: int, converted: int}> $shares
     */
    public function split(SplitMode $mode, array $shares): void
    {
        $this->splitMode = $mode;
        $this->shares->clear();
        foreach ($shares as $share) {
            $this->shares->add(new ExpenseShare($this, $share['participant'], $share['weight'], $share['amount'], $share['converted']));
        }
    }

    /** What it changes to a participant's balance, in cents of the account's currency: positive, they are owed more. */
    public function effectOn(Participant $participant): int
    {
        $effect = $this->paidBy === $participant ? $this->convertedAmount : 0;
        $effect -= $this->shareOf($participant)?->getConvertedAmount() ?? 0;

        return $this->kind->sign() * $effect;
    }

    /** Whether it concerns them: they paid it, or it was (partly) for them. */
    public function involves(Participant $participant): bool
    {
        return $this->paidBy === $participant || null !== $this->shareOf($participant);
    }

    public function getCreatedBy(): ?Member
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedBy(): ?Member
    {
        return $this->updatedBy;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(Member $by, \DateTimeImmutable $at): void
    {
        $this->updatedBy = $by;
        $this->updatedAt = $at;
    }
}
