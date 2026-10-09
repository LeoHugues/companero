<?php

namespace App\Security;

use App\Entity\CatalogItem;
use App\Entity\CharterRule;
use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A member can only see and touch what belongs to their own household.
 *
 * @extends Voter<string, Task|Zone|Pet|Completion|CatalogItem|CharterRule>
 */
final class HouseholdVoter extends Voter
{
    public const ACCESS = 'HOUSEHOLD_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute
            && ($subject instanceof Task || $subject instanceof Zone || $subject instanceof Pet || $subject instanceof Completion || $subject instanceof CatalogItem || $subject instanceof CharterRule);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $member = $token->getUser();
        if (!$member instanceof Member) {
            return false;
        }

        return match (true) {
            $subject instanceof Completion => $member->belongsTo($subject->getTask()->getHousehold()),
            default => $member->belongsTo($subject->getHousehold()),
        };
    }
}
