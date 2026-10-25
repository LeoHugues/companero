<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Allocation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AllocationTest extends TestCase
{
    /** @return iterable<array{int, array<int, int>, array<int, int>}> */
    public static function splits(): iterable
    {
        yield 'evenly' => [3000, [1 => 1, 2 => 1, 3 => 1], [1 => 1000, 2 => 1000, 3 => 1000]];
        yield 'a cent left: the first one pays it' => [1000, [1 => 1, 2 => 1, 3 => 1], [1 => 334, 2 => 333, 3 => 333]];
        yield 'two cents left' => [1001, [7 => 1, 3 => 1, 5 => 1], [7 => 334, 3 => 334, 5 => 333]];
        yield 'by parts' => [4800, [1 => 1, 2 => 1, 3 => 2, 4 => 1], [1 => 960, 2 => 960, 3 => 1920, 4 => 960]];
        yield 'the largest remainder first' => [100, [1 => 1, 2 => 2], [1 => 33, 2 => 67]];
        yield 'no part, nothing' => [1000, [1 => 0, 2 => 1], [1 => 0, 2 => 1000]];
        yield 'proportionally to amounts' => [10176, [1 => 2400, 2 => 2400, 3 => 2400, 4 => 2400], [1 => 2544, 2 => 2544, 3 => 2544, 4 => 2544]];
    }

    /**
     * @param array<int, int> $weights
     * @param array<int, int> $expected
     */
    #[DataProvider('splits')]
    public function testNoCentIsLostNorInvented(int $total, array $weights, array $expected): void
    {
        $shares = Allocation::split($total, $weights);

        self::assertSame($expected, $shares);
        self::assertSame($total, array_sum($shares));
    }

    public function testThereMustBeSomethingToSplitBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Allocation::split(1000, [1 => 0]);
    }
}
