<?php

namespace App\Enum;

enum PetSpecies: string
{
    case Cat = 'cat';
    case Dog = 'dog';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cat => 'Chat',
            self::Dog => 'Chien',
            self::Other => 'Autre',
        };
    }
}
