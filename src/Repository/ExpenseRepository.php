<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\Ledger;
use App\Entity\Member;
use App\Entity\Participant;
use App\Enum\ExpenseKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Expense> */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /** @return list<Expense> with their shares, the latest first */
    public function findForLedger(Ledger $ledger): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('s')
            ->leftJoin('e.shares', 's')
            ->where('e.ledger = :ledger')
            ->setParameter('ledger', $ledger)
            ->orderBy('e.spentOn', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Where everyone stands, in cents of their account's currency: what they paid (or handed over), minus their
     * shares; the other way round for an income.
     *
     * @param list<Ledger> $ledgers
     *
     * @return array<int, int> participant id => cents, owed to them when positive
     */
    public function balances(array $ledgers): array
    {
        if ([] === $ledgers) {
            return [];
        }
        $signed = 'SUM(CASE WHEN e.kind = :income THEN 0 - %1$s.convertedAmount ELSE %1$s.convertedAmount END)';

        $paid = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.paidBy) AS participant', \sprintf($signed, 'e').' AS total')
            ->where('e.ledger IN (:ledgers)')
            ->groupBy('e.paidBy')
            ->setParameter('ledgers', $ledgers)
            ->setParameter('income', ExpenseKind::Income->value)
            ->getQuery()
            ->getArrayResult();
        $owed = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(s.participant) AS participant', \sprintf($signed, 's').' AS total')
            ->from(ExpenseShare::class, 's')
            ->join('s.expense', 'e')
            ->where('e.ledger IN (:ledgers)')
            ->groupBy('s.participant')
            ->setParameter('ledgers', $ledgers)
            ->setParameter('income', ExpenseKind::Income->value)
            ->getQuery()
            ->getArrayResult();

        $balances = [];
        foreach ($paid as $row) {
            $balances[(int) $row['participant']] = (int) $row['total'];
        }
        foreach ($owed as $row) {
            $balances[(int) $row['participant']] = ($balances[(int) $row['participant']] ?? 0) - (int) $row['total'];
        }

        return $balances;
    }

    /**
     * What was spent in each account, incomes taken off, transfers aside.
     *
     * @param list<Ledger> $ledgers
     *
     * @return array<int, int> ledger id => cents
     */
    public function totals(array $ledgers): array
    {
        if ([] === $ledgers) {
            return [];
        }
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.ledger) AS ledger', 'SUM(CASE WHEN e.kind = :income THEN 0 - e.convertedAmount ELSE e.convertedAmount END) AS total')
            ->where('e.ledger IN (:ledgers)')
            ->andWhere('e.kind <> :transfer')
            ->groupBy('e.ledger')
            ->setParameter('ledgers', $ledgers)
            ->setParameter('income', ExpenseKind::Income->value)
            ->setParameter('transfer', ExpenseKind::Transfer->value)
            ->getQuery()
            ->getArrayResult();

        return array_combine(array_map(static fn (array $row): int => (int) $row['ledger'], $rows), array_map(static fn (array $row): int => (int) $row['total'], $rows));
    }

    /** Whether a participant paid something or had a share of something: then they stay. */
    public function involves(Participant $participant): bool
    {
        $paid = $this->count(['paidBy' => $participant]);
        if ($paid > 0) {
            return true;
        }

        return $this->getEntityManager()->getRepository(ExpenseShare::class)->count(['participant' => $participant]) > 0;
    }

    public function countForLedger(Ledger $ledger): int
    {
        return $this->count(['ledger' => $ledger]);
    }

    /**
     * What others noted lately that concerns a member: what they paid for them, received for them, or handed them.
     *
     * @return list<Expense> the latest first
     */
    public function findNotedForSince(Member $member, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.ledger', 'l')
            ->join('l.participants', 'p', 'WITH', 'p.member = :member')
            ->leftJoin('e.shares', 's', 'WITH', 's.participant = p')
            ->where('e.createdAt >= :since')
            ->andWhere('e.createdBy IS NULL OR e.createdBy <> :member')
            ->andWhere('e.paidBy = p OR s.id IS NOT NULL')
            ->setParameter('member', $member)
            ->setParameter('since', $since)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
