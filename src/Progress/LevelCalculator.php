<?php

namespace App\Progress;

/** Turns experience points (every point ever earned) into a level and a funny rank. */
final class LevelCalculator
{
    private const RANKS = [
        1 => 'Nouvelle recrue',
        2 => 'Coup de balai',
        3 => 'Éponge en herbe',
        4 => 'Plumeau curieux',
        5 => 'Apprenti balai',
        6 => 'Ninja du chiffon',
        7 => 'Maître des miettes',
        8 => 'Chevalier de la serpillière',
        9 => 'Gardien de la Casa',
        10 => 'Légende de la coloc',
    ];

    public function forXp(int $xp): Level
    {
        $xp = max(0, $xp);
        $number = 1;
        while (self::threshold($number + 1) <= $xp) {
            ++$number;
        }

        return new Level($number, self::rank($number), $xp, self::threshold($number), self::threshold($number + 1), self::rank($number + 1));
    }

    /** XP needed to reach a level: 0, 200, 600, 1 200, 2 000… each level asks a bit more than the previous one. */
    public static function threshold(int $level): int
    {
        return 100 * $level * ($level - 1);
    }

    private static function rank(int $level): string
    {
        return self::RANKS[min($level, array_key_last(self::RANKS))];
    }
}
