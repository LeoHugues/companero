<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Currency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    /** @return iterable<array{string, ?string}> */
    public static function typed(): iterable
    {
        yield ['12,50', '12.50'];
        yield ['12.5', '12.5'];
        yield ['12', '12'];
        yield [' 1 234,56 € ', '1234.56'];
        yield ["1\u{202F}234,56", '1234.56'];
        yield ['1.234,56', '1234.56'];
        yield ['1,234.56', '1234.56'];
        yield ['$12', '12'];
        yield [',5', '0.5'];
        yield ['12,345', null];
        yield ['douze', null];
        yield ['12e3', null];
        yield ['', null];
    }

    #[DataProvider('typed')]
    public function testAmountsAreReadAsTheyAreTyped(string $typed, ?string $decimal): void
    {
        self::assertSame($decimal, Currency::parse($typed));
    }

    public function testRatesHaveMoreDecimals(): void
    {
        self::assertSame('0.923456', Currency::parse('0,923456', 6));
        self::assertNull(Currency::parse('0,9234567', 6));
    }

    public function testAmountsAreKeptInCents(): void
    {
        self::assertSame(1250, Currency::toCents('12.5'));
        self::assertSame(1205, Currency::toCents('12.05'));
        self::assertSame(1200, Currency::toCents('12'));
    }

    public function testAmountsAreWrittenTheFrenchWay(): void
    {
        self::assertSame("1\u{202F}234,50\u{A0}€", Currency::format(123450, 'EUR'));
        self::assertSame("+12,00\u{A0}$", Currency::format(1200, 'USD', true));
        self::assertSame("−3,20\u{A0}£", Currency::format(-320, 'GBP'));
        self::assertSame("0,00\u{A0}CHF", Currency::format(0, 'CHF', true));
    }
}
