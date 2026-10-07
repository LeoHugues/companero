<?php

namespace App\Twig;

use App\Household\MemberPalette;
use Twig\Attribute\AsTwigFilter;

final class MemberColors
{
    #[AsTwigFilter('ink')]
    public static function ink(string $color): string
    {
        return MemberPalette::inkFor($color);
    }
}
