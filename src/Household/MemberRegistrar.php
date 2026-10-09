<?php

namespace App\Household;

use App\Entity\Household;
use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class MemberRegistrar
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function register(Household $household, Registration $registration): Member
    {
        $member = $this->newMember($household, $registration->name);

        return $this->claim($member, $registration);
    }

    /** Someone takes the profile that was waiting for them, and everything attached to it. */
    public function claim(Member $member, Registration $registration): Member
    {
        $member->claim($registration->username, $this->passwordHasher->hashPassword($member, $registration->plainPassword));
        $this->entityManager->flush();

        return $member;
    }

    /** A coloc known by their first name only, until they claim their profile with the invitation link. */
    public function addUnclaimed(Household $household, string $name): Member
    {
        $member = $this->newMember($household, $name);
        $this->entityManager->flush();

        return $member;
    }

    private function newMember(Household $household, string $name): Member
    {
        $member = new Member($household, trim($name), MemberPalette::colorAt($household->getMembers()->count()), $this->clock->now());
        $this->entityManager->persist($member);

        return $member;
    }
}
