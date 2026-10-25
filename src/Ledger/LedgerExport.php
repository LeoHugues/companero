<?php

namespace App\Ledger;

use App\Entity\Ledger;
use App\Repository\ExpenseRepository;

/**
 * An account in a spreadsheet, the oldest line first: what, when, who paid, how much, then what it changes for each
 * participant (positive: owed to them). Semicolons and a byte order mark: it opens as it is in a French spreadsheet.
 */
final readonly class LedgerExport
{
    public function __construct(
        private ExpenseRepository $expenses,
    ) {
    }

    public function csv(Ledger $ledger): string
    {
        $participants = $ledger->getParticipants()->toArray();
        $currency = $ledger->getCurrency();
        $rows = [[
            'Date', 'Titre', 'Type', 'Catégorie', 'Montant', 'Devise', 'Taux', 'Montant ('.$currency.')', 'Payé par', 'Pour',
            ...array_map(static fn ($participant): string => $participant->getName().' ('.$currency.')', $participants),
        ]];

        foreach (array_reverse($this->expenses->findForLedger($ledger)) as $expense) {
            $rows[] = [
                $expense->getSpentOn()->format('d/m/Y'),
                $expense->getTitle(),
                $expense->getKind()->label(),
                $expense->getCategory()?->label() ?? '',
                self::decimal($expense->getAmount()),
                $expense->getCurrency(),
                null === $expense->getExchangeRate() ? '' : Currency::rate($expense->getExchangeRate()),
                self::decimal($expense->getConvertedAmount()),
                $expense->getPaidBy()->getName(),
                implode(', ', array_map(static fn ($share): string => $share->getParticipant()->getName(), $expense->getShares()->toArray())),
                ...array_map(static fn ($participant): string => self::decimal($expense->effectOn($participant)), $participants),
            ];
        }

        $stream = fopen('php://temp', 'r+') ?: throw new \RuntimeException('No memory to write the export.');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }
        rewind($stream);

        return "\u{FEFF}".stream_get_contents($stream);
    }

    /** "-12,50": a spreadsheet reads it as a number. */
    private static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }
}
