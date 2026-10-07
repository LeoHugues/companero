<?php

namespace App\Household;

/** Each member gets a colour of the Casa palette, used in the shared progress bars. */
final class MemberPalette
{
    private const COLORS = ['#E8692C', '#F6C453', '#8C5A3C', '#D9B48C', '#B4521F', '#C9B8A3'];
    private const DARK = ['#8C5A3C', '#B4521F'];

    public static function colorAt(int $index): string
    {
        return self::COLORS[$index % \count(self::COLORS)];
    }

    /** Text colour readable on top of a member colour. */
    public static function inkFor(string $color): string
    {
        return \in_array($color, self::DARK, true) ? '#FFF6E9' : '#2E1E14';
    }
}
