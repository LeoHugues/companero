<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** What one participant's part of an expense is: in its currency, and in the account's. */
#[ORM\Entity]
#[ORM\Table(name: 'expense_share')]
class ExpenseShare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Expense $expense;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Participant $participant;

    /** Their number of parts, split by parts; 1 otherwise. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private int $weight;

    /** In cents of the expense's currency. */
    #[ORM\Column]
    private int $amount;

    /** In cents of the account's currency. */
    #[ORM\Column]
    private int $convertedAmount;

    public function __construct(Expense $expense, Participant $participant, int $weight, int $amount, int $convertedAmount)
    {
        $this->expense = $expense;
        $this->participant = $participant;
        $this->weight = $weight;
        $this->amount = $amount;
        $this->convertedAmount = $convertedAmount;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpense(): Expense
    {
        return $this->expense;
    }

    public function getParticipant(): Participant
    {
        return $this->participant;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getConvertedAmount(): int
    {
        return $this->convertedAmount;
    }
}
