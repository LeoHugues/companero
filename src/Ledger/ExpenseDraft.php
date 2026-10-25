<?php

namespace App\Ledger;

use App\Entity\Expense;
use App\Entity\Ledger;
use App\Entity\Participant;
use App\Enum\ExpenseCategory;
use App\Enum\ExpenseKind;
use App\Enum\SplitMode;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What the expense form says, before it is written down: what, how much, who paid, who it was for and how it is
 * split (equally, by parts, by amounts). For a transfer, who handed the money to whom.
 */
final class ExpenseDraft
{
    /** 10 million: a typo, surely. */
    public const MAX_AMOUNT = 1_000_000_000;

    public ExpenseKind $kind = ExpenseKind::Expense;

    #[Assert\Length(max: Expense::TITLE_MAX_LENGTH)]
    public ?string $title = null;

    /** In cents of $currency. */
    public ?int $amount = null;

    public string $currency;

    /** One unit of $currency in the account's currency, as a decimal: "0.92". */
    public ?string $exchangeRate = null;

    public ?Participant $paidBy = null;

    /** For a transfer. */
    public ?Participant $recipient = null;

    public SplitMode $splitMode = SplitMode::Equal;

    /** @var array<int, ShareInput> by participant id, in their order */
    public array $split = [];

    #[Assert\NotNull(message: 'Quel jour ?')]
    public ?\DateTimeImmutable $spentOn = null;

    /** None chosen: guessed from the title. */
    public ?ExpenseCategory $category = null;

    private function __construct(
        public readonly Ledger $ledger,
    ) {
        $this->currency = $ledger->getCurrency();
        foreach ($ledger->getParticipants() as $participant) {
            $this->split[(int) $participant->getId()] = new ShareInput();
        }
    }

    /** Paid today, by the one who notes it, for everyone. */
    public static function new(Ledger $ledger, ?Participant $me, \DateTimeImmutable $today): self
    {
        $draft = new self($ledger);
        $draft->paidBy = $me;
        $draft->spentOn = $today->setTime(0, 0);

        return $draft;
    }

    /** A reimbursement, as suggested by the balances (the amount can be changed). */
    public static function transfer(Ledger $ledger, ?Participant $from, ?Participant $to, ?int $amount, \DateTimeImmutable $today): self
    {
        $draft = self::new($ledger, $from, $today);
        $draft->kind = ExpenseKind::Transfer;
        $draft->recipient = $to;
        $draft->amount = $amount;

        return $draft;
    }

    public static function of(Expense $expense): self
    {
        $draft = new self($expense->getLedger());
        $draft->kind = $expense->getKind();
        $draft->title = $expense->getTitle();
        $draft->amount = $expense->getAmount();
        $draft->currency = $expense->getCurrency();
        $draft->exchangeRate = $expense->getExchangeRate();
        $draft->paidBy = $expense->getPaidBy();
        $draft->recipient = $expense->getRecipient();
        $draft->splitMode = $expense->getSplitMode();
        $draft->spentOn = $expense->getSpentOn();
        $draft->category = $expense->getCategory();
        if ($expense->getKind()->isShared()) {
            foreach ($draft->split as $id => $input) {
                $share = null;
                foreach ($expense->getShares() as $candidate) {
                    if ($candidate->getParticipant()->getId() === $id) {
                        $share = $candidate;
                    }
                }
                $draft->split[$id] = null === $share
                    ? new ShareInput(false)
                    : new ShareInput(true, $share->getWeight(), $share->getAmount());
            }
        }

        return $draft;
    }

    public function isForeign(): bool
    {
        return $this->currency !== $this->ledger->getCurrency();
    }

    /**
     * Each one's part, in cents of the expense's currency, if the split adds up.
     *
     * @return array<int, array{weight: int, amount: int}>|null by participant id
     */
    public function shares(): ?array
    {
        $included = array_filter($this->split, static fn (ShareInput $input): bool => $input->included);
        if ([] === $included || null === $this->amount || $this->amount <= 0) {
            return null;
        }

        // Ticked with nothing (no part, no amount): not in it after all.
        if (SplitMode::Amounts === $this->splitMode) {
            $amounts = array_filter(array_map(static fn (ShareInput $input): int => max(0, $input->amount ?? 0), $included));
            if (array_sum($amounts) !== $this->amount) {
                return null;
            }

            return array_map(static fn (int $amount): array => ['weight' => 1, 'amount' => $amount], $amounts);
        }

        $weights = SplitMode::Shares === $this->splitMode
            ? array_filter(array_map(static fn (ShareInput $input): int => max(0, $input->weight ?? 0), $included))
            : array_map(static fn (): int => 1, $included);
        if ([] === $weights) {
            return null;
        }
        $amounts = Allocation::split($this->amount, $weights);
        $shares = [];
        foreach ($weights as $id => $weight) {
            $shares[$id] = ['weight' => $weight, 'amount' => $amounts[$id]];
        }

        return $shares;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $transfer = ExpenseKind::Transfer === $this->kind;
        if (!$transfer && '' === trim((string) $this->title)) {
            $context->buildViolation(ExpenseKind::Income === $this->kind ? 'D’où vient cet argent ?' : 'C’était pour quoi ?')->atPath('title')->addViolation();
        }
        if (null === $this->amount || $this->amount <= 0) {
            $context->buildViolation('Combien ? Un montant comme 12,50.')->atPath('amount')->addViolation();
        } elseif ($this->amount > self::MAX_AMOUNT) {
            $context->buildViolation('C’est beaucoup ! Une faute de frappe ?')->atPath('amount')->addViolation();
        }
        if ($this->isForeign() && (null === $this->exchangeRate || (float) $this->exchangeRate <= 0)) {
            $context->buildViolation(\sprintf('Combien vaut 1 %s en %s ?', Currency::symbol($this->currency), Currency::symbol($this->ledger->getCurrency())))->atPath('exchangeRate')->addViolation();
        }
        if (null === $this->paidBy) {
            $context->buildViolation(match ($this->kind) {
                ExpenseKind::Expense => 'Qui a payé ?',
                ExpenseKind::Income => 'Qui a reçu l’argent ?',
                ExpenseKind::Transfer => 'Qui a donné l’argent ?',
            })->atPath('paidBy')->addViolation();
        }

        if ($transfer) {
            if (null === $this->recipient) {
                $context->buildViolation('À qui ?')->atPath('recipient')->addViolation();
            } elseif ($this->recipient === $this->paidBy) {
                $context->buildViolation('À quelqu’un d’autre que soi-même !')->atPath('recipient')->addViolation();
            }

            return;
        }

        $included = array_filter($this->split, static fn (ShareInput $input): bool => $input->included);
        if ([] === $included) {
            $context->buildViolation('Pour qui ? Coche au moins une personne.')->atPath('split')->addViolation();
        } elseif (SplitMode::Shares === $this->splitMode && array_sum(array_map(static fn (ShareInput $input): int => max(0, $input->weight ?? 0), $included)) <= 0) {
            $context->buildViolation('Au moins une part, pour quelqu’un.')->atPath('split')->addViolation();
        } elseif (SplitMode::Amounts === $this->splitMode && null !== $this->amount && $this->amount > 0) {
            $gap = $this->amount - array_sum(array_map(static fn (ShareInput $input): int => max(0, $input->amount ?? 0), $included));
            if ($gap > 0) {
                $context->buildViolation(\sprintf('Il reste %s à répartir.', Currency::format($gap, $this->currency)))->atPath('split')->addViolation();
            } elseif ($gap < 0) {
                $context->buildViolation(\sprintf('C’est %s de trop.', Currency::format(-$gap, $this->currency)))->atPath('split')->addViolation();
            }
        }
    }
}
