<?php

namespace App\Ledger;

use Symfony\Component\Intl\Currencies;

/**
 * The currencies an account can be kept in, or an expense paid in (on a trip): those counted in cents. Amounts are
 * kept in cents, and written the French way: "1 234,50 €".
 */
final class Currency
{
    public const DEFAULT = 'EUR';

    private const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'CHF' => 'CHF',
        'CAD' => '$ CA',
        'AUD' => '$ AU',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'PLN' => 'zł',
        'CZK' => 'Kč',
        'MAD' => 'MAD',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::SYMBOLS);
    }

    /** @return array<string, string> "Euro (€)" => "EUR" */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::SYMBOLS as $code => $symbol) {
            $name = mb_ucfirst(Currencies::getName($code, 'fr'));
            $choices[$code === $symbol ? $name.' ('.$code.')' : \sprintf('%s (%s, %s)', $name, $symbol, $code)] = $code;
        }

        return $choices;
    }

    /** @return array<string, string> code => symbol */
    public static function symbols(): array
    {
        return self::SYMBOLS;
    }

    public static function symbol(string $code): string
    {
        return self::SYMBOLS[$code] ?? $code;
    }

    /** "12,50 €", "1 234,00 $"; signed, "+12,50 €" and "−3,20 €". */
    public static function format(int $cents, string $code, bool $signed = false): string
    {
        $sign = match (true) {
            $cents < 0 => '−',
            $signed && $cents > 0 => '+',
            default => '',
        };

        return $sign.number_format(abs($cents) / 100, 2, ',', "\u{202F}")."\u{A0}".self::symbol($code);
    }

    /**
     * What is typed into an amount, as a decimal string: "12,5", "12.50", "1 234,56", "1.234,56 €" all read.
     *
     * @return string|null "1234.56", null if it is not a number with at most $scale decimals
     */
    public static function parse(string $typed, int $scale = 2): ?string
    {
        // Spaces group the thousands; a symbol or a code may stand before or after ("12 €", "$12").
        $value = (string) preg_replace(['/[\s\x{A0}\x{202F}]/u', '/^[^\d,.]+|[^\d]+$/u'], '', $typed);
        // Both separators: the first one only groups the thousands.
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if (false !== $comma && false !== $dot) {
            $value = str_replace($comma > $dot ? '.' : ',', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (str_starts_with($value, '.')) {
            $value = '0'.$value;
        }

        return 1 === preg_match('/^\d+(\.\d{1,'.$scale.'})?$/', $value) ? $value : null;
    }

    /** An exchange rate as it is kept, "0.920000", written "0,92". */
    public static function rate(string $rate): string
    {
        return str_replace('.', ',', rtrim(rtrim($rate, '0'), '.'));
    }

    /** "12.5" is 1250 cents. */
    public static function toCents(string $decimal): int
    {
        [$units, $fraction] = explode('.', $decimal.'.');

        return (int) $units * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
