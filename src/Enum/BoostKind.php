<?php

namespace App\Enum;

enum BoostKind: string
{
    /** Extra points: they count for the weekly goal and the XP. */
    case Points = 'points';
    /** Extra XP only. */
    case Xp = 'xp';
}
