<?php

namespace App\Enum;

/** What a member can earn by levelling up. */
enum GiftKind: string
{
    /** Everyone earns a third more on their tasks for a day. */
    case TeamBoost = 'team_boost';
    /** Someone else (never yourself) earns a third more on their tasks for a day. */
    case FriendBoost = 'friend_boost';
    /** A third more XP for a day, without counting towards the weekly goal. */
    case XpBoost = 'xp_boost';
    /** Saves the weekly streak once, when the goal is missed. Can be offered. */
    case StreakFreeze = 'streak_freeze';
    /** For the pets. */
    case Treat = 'treat';
    /** A yellow card to give a coloc, about something in particular. Symbolic: no points lost. */
    case YellowCard = 'yellow_card';

    public function label(): string
    {
        return match ($this) {
            self::TeamBoost => 'Boost coloc',
            self::FriendBoost => 'Boost ciblé',
            self::XpBoost => 'Boost d’XP',
            self::StreakFreeze => 'Gel de série',
            self::Treat => 'Friandise',
            self::YellowCard => 'Carton jaune',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TeamBoost => '+1 pt tous les 3 pts pour toute la coloc, pendant 24 h',
            self::FriendBoost => '+1 pt tous les 3 pts pour un coloc de ton choix, pendant 24 h',
            self::XpBoost => '+1 XP tous les 3 pts pour toi, pendant 24 h',
            self::StreakFreeze => 'Sauve ta série une semaine où l’objectif n’est pas atteint',
            self::Treat => 'Une petite douceur pour les animaux de la coloc',
            self::YellowCard => 'À donner à un coloc, pour un truc en particulier. Pour rire : aucun point perdu',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TeamBoost, self::FriendBoost, self::XpBoost => 'bolt',
            self::StreakFreeze => 'snow',
            self::Treat => 'paw',
            self::YellowCard => 'card',
        };
    }
}
