<?php

namespace App\Enum;

/** What an expense went on, with its drawing on its pastel tile (like the categories of the tasks). */
enum ExpenseCategory: string
{
    case Groceries = 'groceries';
    case Rent = 'rent';
    case Energy = 'energy';
    case Internet = 'internet';
    case House = 'house';
    case Restaurant = 'restaurant';
    case Transport = 'transport';
    case Leisure = 'leisure';
    case Health = 'health';
    case Pets = 'pets';
    case Gifts = 'gifts';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Groceries => 'Courses',
            self::Rent => 'Logement',
            self::Energy => 'Énergie et eau',
            self::Internet => 'Internet et abonnements',
            self::House => 'Maison',
            self::Restaurant => 'Restaurant et sorties',
            self::Transport => 'Transport',
            self::Leisure => 'Loisirs',
            self::Health => 'Santé',
            self::Pets => 'Animaux',
            self::Gifts => 'Cadeaux',
            self::Other => 'Autre',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Groceries => 'basket',
            self::Rent => 'key',
            self::Energy => 'bolt',
            self::Internet => 'wifi',
            self::House => 'sponge',
            self::Restaurant => 'fork',
            self::Transport => 'car',
            self::Leisure => 'ticket',
            self::Health => 'heart',
            self::Pets => 'paw',
            self::Gifts => 'gift',
            self::Other => 'coins',
        };
    }

    public function tile(): string
    {
        return match ($this) {
            self::Groceries => 'tile-sage',
            self::Rent, self::Other => 'tile-sand',
            self::Energy, self::House => 'tile-sun',
            self::Internet, self::Transport => 'tile-sky',
            self::Restaurant, self::Health, self::Pets => 'tile-blush',
            self::Leisure, self::Gifts => 'tile-lilac',
        };
    }
}
