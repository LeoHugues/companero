<?php

namespace App\Security;

use App\Entity\Absence;
use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\Task;
use App\Entity\Zone;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A member can only see and touch what belongs to their own household
 * (and only their own absences).
 *
 * @extends Voter<string, Task|Zone|Completion|Absence>
 */
final class HouseholdVoter extends Voter
{
    public const ACCESS = 'HOUSEHOLD_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute
            && ($subject instanceof Task || $subject instanceof Zone || $subject instanceof Completion || $subject instanceof Absence);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $member = $token->getUser();
        if (!$member instanceof Member) {
            return false;
        }

        return match (true) {
            $subject instanceof Absence => $subject->getMember() === $member,
            $subject instanceof Completion => $member->belongsTo($subject->getTask()->getHousehold()),
            default => $member->belongsTo($subject->getHousehold()),
        };
    }
}
