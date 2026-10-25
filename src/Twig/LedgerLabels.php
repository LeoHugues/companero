<?php

namespace App\Twig;

use App\Entity\Expense;
use App\Ledger\Currency;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/** Money the French way, the days of an account, the drawing of an expense. */
final readonly class LedgerLabels
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /** {{ 1250|money('EUR') }} is "12,50 €"; signed, "+12,50 €". */
    #[AsTwigFilter('money')]
    public function money(int $cents, string $currency, bool $signed = false): string
    {
        return Currency::format($cents, $currency, $signed);
    }

    /** "0.920000" is "0,92". */
    #[AsTwigFilter('rate')]
    public function rate(string $rate): string
    {
        return Currency::rate($rate);
    }

    #[AsTwigFunction('currency_symbol')]
    public function symbol(string $currency): string
    {
        return Currency::symbol($currency);
    }

    /** "Aujourd’hui", "Hier", "Mardi 20 octobre", "Lundi 2 décembre 2024" another year. */
    #[AsTwigFunction('ledger_day')]
    public function day(\DateTimeImmutable $on): string
    {
        $today = $this->clock->now()->setTime(0, 0);
        $days = (int) $on->setTime(0, 0)->diff($today)->format('%r%a');
        if (0 === $days) {
            return 'Aujourd’hui';
        }
        if (1 === $days) {
            return 'Hier';
        }
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $day = \sprintf('%s %d %s', mb_ucfirst(TaskLabels::weekday((int) $on->format('N'))), (int) $on->format('j'), $months[(int) $on->format('n') - 1]);

        return $on->format('Y') === $today->format('Y') ? $day : $day.' '.$on->format('Y');
    }

    /** @return array{icon: string, tile: string} */
    #[AsTwigFunction('expense_art')]
    public function art(Expense $expense): array
    {
        $category = $expense->getCategory();
        if ($expense->isTransfer() || null === $category) {
            return ['icon' => 'swap', 'tile' => 'tile-sage'];
        }

        return ['icon' => $category->icon(), 'tile' => $category->tile()];
    }
}
