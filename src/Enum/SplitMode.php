<?php

namespace App\Enum;

/** How an expense is split between the people it is for. */
enum SplitMode: string
{
    case Equal = 'equal';
    case Shares = 'shares';
    case Amounts = 'amounts';

    public function label(): string
    {
        return match ($this) {
            self::Equal => 'À parts égales',
            self::Shares => 'Par parts',
            self::Amounts => 'Par montants',
        };
    }
}
