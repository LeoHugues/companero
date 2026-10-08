<?php

namespace App\Twig;

use App\Entity\Member;
use App\Entity\YellowCard;
use App\Repository\YellowCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/** The yellow cards a member received and has not seen yet: shown once, in big, on the next page. */
final readonly class YellowCardNotice
{
    public function __construct(
        private Security $security,
        private YellowCardRepository $cards,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<YellowCard> marked as seen as they are handed to the page */
    #[AsTwigFunction('yellow_cards_to_show')]
    public function toShow(): array
    {
        $member = $this->security->getUser();
        if (!$member instanceof Member) {
            return [];
        }
        $cards = $this->cards->findUnseenBy($member);
        if ([] !== $cards) {
            $now = $this->clock->now();
            foreach ($cards as $card) {
                $card->markSeen($now);
            }
            $this->entityManager->flush();
        }

        return $cards;
    }
}
