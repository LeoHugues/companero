<?php

namespace App\Tests\Unit\Ledger;

use App\Entity\Household;
use App\Entity\Ledger;
use App\Entity\Participant;
use App\Ledger\BalanceSheet;
use App\Ledger\Settlement;
use PHPUnit\Framework\TestCase;

final class BalanceSheetTest extends TestCase
{
    public function testTheOneWhoOwesTheMostPaysTheOneWhoIsOwedTheMost(): void
    {
        [$ledger, $people] = self::ledger('Léa', 'Léo', 'Robin', 'Gab');
        $sheet = new BalanceSheet($ledger, [1 => -3000, 2 => 5000, 3 => -500, 4 => -1500]);

        self::assertSame([
            ['Léa', 'Léo', 3000],
            ['Gab', 'Léo', 1500],
            ['Robin', 'Léo', 500],
        ], self::read($sheet->settlements()));
        self::assertSame(5000, $sheet->largest());
        self::assertFalse($sheet->isEven());
        self::assertSame(-500, $sheet->of($people['Robin']));
    }

    public function testNoOneHandsMoneyOnNeedlessly(): void
    {
        [$ledger, $people] = self::ledger('A', 'B', 'C', 'D');
        // Two pairs: each debtor pays one creditor, at most one reimbursement fewer than there are people.
        $sheet = new BalanceSheet($ledger, [1 => 2000, 2 => -2000, 3 => 700, 4 => -700]);

        self::assertSame([['B', 'A', 2000], ['D', 'C', 700]], self::read($sheet->settlements()));
        self::assertSame(['B', 'A', 2000], self::read([$sheet->settlementBetween($people['B'], $people['A']) ?? throw new \LogicException()])[0]);
        self::assertNull($sheet->settlementBetween($people['A'], $people['B']));
    }

    public function testEveryoneEvenHasNothingToSettle(): void
    {
        [$ledger] = self::ledger('A', 'B');
        $sheet = new BalanceSheet($ledger, [1 => 0]);

        self::assertTrue($sheet->isEven());
        self::assertSame([], $sheet->settlements());
        self::assertSame(0, $sheet->largest());
    }

    /** @return array{Ledger, array<string, Participant>} participants numbered from 1, in order */
    private static function ledger(string ...$names): array
    {
        $ledger = new Ledger(new Household('La coloc', new \DateTimeImmutable()), 'Dépenses communes', new \DateTimeImmutable());
        $people = [];
        foreach ($names as $i => $name) {
            $people[$name] = $ledger->addParticipant($name);
            (new \ReflectionProperty(Participant::class, 'id'))->setValue($people[$name], $i + 1);
        }

        return [$ledger, $people];
    }

    /**
     * @param list<Settlement> $settlements
     *
     * @return list<array{string, string, int}>
     */
    private static function read(array $settlements): array
    {
        return array_map(static fn (Settlement $settlement): array => [$settlement->from->getName(), $settlement->to->getName(), $settlement->amount], $settlements);
    }
}
