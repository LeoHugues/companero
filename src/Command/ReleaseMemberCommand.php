<?php

namespace App\Command;

use App\Entity\Member;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Turns accounts back into profiles to claim: their username and password are forgotten,
 * everything else stays (points, tasks, pets…). Their coloc then takes them back with the
 * invitation link ("Je suis Robin").
 */
#[AsCommand(name: 'app:membre:a-reclamer', description: 'Remet des profils « à réclamer » avec le lien d’invitation, sans rien perdre de ce qui leur est attaché')]
final readonly class ReleaseMemberCommand
{
    public function __construct(
        private MemberRepository $members,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @param list<string> $who */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Le pseudo ou le prénom de chaque coloc à libérer')] array $who,
    ): int {
        $released = [];
        foreach ($who as $someone) {
            $member = $this->find($io, $someone);
            if (null === $member) {
                return 1;
            }
            $released[$member->getId()] = $member;
        }

        foreach ($released as $member) {
            $household = $member->getHousehold();
            $stillIn = array_filter($household->getMembers()->toArray(), static fn (Member $other): bool => $other->isClaimed() && !isset($released[$other->getId()]));
            if ([] === $stillIn) {
                $io->error(\sprintf('Plus personne ne pourrait se connecter à « %s » : gardez au moins un compte.', $household->getName()));

                return 1;
            }
        }

        foreach ($released as $member) {
            $member->release();
        }
        $this->entityManager->flush();

        $io->success(\sprintf('À réclamer : %s.', implode(', ', array_map(static fn (Member $member): string => $member->getName(), $released))));
        $household = reset($released)->getHousehold();
        $io->text(['Ils choisissent leur profil avec le lien d’invitation :', $this->urls->generate('onboarding_join', ['token' => $household->getInviteToken()], UrlGeneratorInterface::ABSOLUTE_URL)]);

        return 0;
    }

    private function find(SymfonyStyle $io, string $someone): ?Member
    {
        $member = $this->members->loadUserByIdentifier($someone);
        if ($member instanceof Member) {
            return $member;
        }

        $byName = array_values(array_filter($this->members->findAll(), static fn (Member $member): bool => Member::normalizeUsername($member->getName()) === Member::normalizeUsername($someone)));
        if (1 === \count($byName)) {
            return $byName[0];
        }

        $io->error([] === $byName ? \sprintf('Personne ne s’appelle « %s ».', $someone) : \sprintf('Plusieurs colocs s’appellent « %s » : donnez son pseudo.', $someone));

        return null;
    }
}
