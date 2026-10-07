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
        $member = new Member(
            $household,
            trim($registration->name),
            $registration->email,
            MemberPalette::colorAt($household->getMembers()->count()),
            $this->clock->now(),
        );
        $member->setPassword($this->passwordHasher->hashPassword($member, $registration->plainPassword));

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $member;
    }
}
