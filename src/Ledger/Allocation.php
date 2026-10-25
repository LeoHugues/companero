<?php

namespace App\Ledger;

/**
 * Splits a sum of cents by weights, without losing or inventing a cent: 10,00 € between three is 3,34 + 3,33 + 3,33.
 * Each gets the cents of its weight rounded down; the cents left over go to the largest remainders, the first ones
 * first when they are equal (the order of the participants). The same rule runs in the form (split_controller.js).
 */
final class Allocation
{
    /**
     * @template K of array-key
     *
     * @param array<K, int> $weights positive or zero, at least one positive
     *
     * @return array<K, int> the same keys, in the same order, summing to $total
     */
    public static function split(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            throw new \InvalidArgumentException('Nothing to split by.');
        }

        $shares = [];
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $exact = $total * $weight;
            $shares[$key] = intdiv($exact, $sum);
            $remainders[$key] = $exact % $sum;
        }

        $left = $total - array_sum($shares);
        // A stable sort: equal remainders keep the participants' order.
        $order = array_keys($remainders);
        usort($order, static fn (int|string $a, int|string $b): int => $remainders[$b] <=> $remainders[$a]);
        foreach (\array_slice($order, 0, $left) as $key) {
            ++$shares[$key];
        }

        return $shares;
    }
}
