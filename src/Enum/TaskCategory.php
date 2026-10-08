<?php

namespace App\Enum;

enum TaskCategory: string
{
    case Cleaning = 'cleaning';
    case Repair = 'repair';
    case Garden = 'garden';
    case Shopping = 'shopping';
    case Pets = 'pets';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cleaning => 'Ménage',
            self::Repair => 'Bricolage',
            self::Garden => 'Jardin',
            self::Shopping => 'Course',
            self::Pets => 'Animaux',
            self::Other => 'Autre',
        };
    }

    /** The icon of its medallion, on the task cards. */
    public function icon(): string
    {
        return match ($this) {
            self::Cleaning => 'broom',
            self::Repair => 'wrench',
            self::Garden => 'leaf',
            self::Shopping => 'cart',
            self::Pets => 'paw',
            self::Other => 'spark',
        };
    }
}
