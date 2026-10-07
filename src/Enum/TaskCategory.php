<?php

namespace App\Enum;

enum TaskCategory: string
{
    case Cleaning = 'cleaning';
    case Repair = 'repair';
    case Garden = 'garden';
    case Shopping = 'shopping';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cleaning => 'Ménage',
            self::Repair => 'Bricolage',
            self::Garden => 'Jardin',
            self::Shopping => 'Course',
            self::Other => 'Autre',
        };
    }
}
