<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Bounty;
use App\Entity\Member;
use App\Progress\LevelCalculator;
use App\Progress\LevelProvider;
use App\Repository\BountyRepository;
use App\Repository\EarnedTitleRepository;
use App\Repository\YellowCardRepository;
use App\Reward\LevelGifts;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Everything there is to win and what was won: the road of levels, the surprises, the titles, the yellow cards. */
final class RewardsController extends AbstractController
{
    /** How many levels ahead the road shows. */
    private const LEVELS_AHEAD = 3;

    #[Route('/recompenses', name: 'rewards_show', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] Member $member,
        LevelProvider $levels,
        LevelGifts $levelGifts,
        BountyRepository $bounties,
        EarnedTitleRepository $titles,
        YellowCardRepository $cards,
        ClockInterface $clock,
    ): Response {
        $household = $member->getHousehold();
        $level = $levels->levelOf($member);
        $hasPets = !$household->getPets()->isEmpty();

        $road = [];
        for ($number = 1; $number <= $level->number + self::LEVELS_AHEAD; ++$number) {
            $road[] = [
                'number' => $number,
                'rank' => LevelCalculator::rank($number),
                'xp' => LevelCalculator::threshold($number),
                'gifts' => $levelGifts->forLevel($number, $hasPets),
            ];
        }
        $thisWeek = array_values($bounties->findForWeek($household, Week::containing($clock->now())->start));

        return $this->render('rewards/show.html.twig', [
            'member' => $member,
            'level' => $level,
            'road' => array_reverse($road),
            'week_bounties' => $thisWeek,
            'found_this_week' => \count(array_filter($thisWeek, static fn (Bounty $bounty): bool => $bounty->isClaimed())),
            'found' => $bounties->findClaimedBy($member),
            'titles' => $titles->findForMember($member),
            'cards_received' => $cards->findReceivedBy($member),
            'cards_given' => $cards->findGivenBy($member),
        ]);
    }
}
