<?php

namespace App\Controller;

use App\Entity\Member;
use App\History\History;
use App\Repository\CompletionRepository;
use App\Repository\PointEntryRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** What I did, or what the household did, over the last 7 or 30 days. */
final class HistoryController extends AbstractController
{
    private const PERIODS = [7, 30];

    #[Route('/fait', name: 'history', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] Member $member,
        CompletionRepository $completions,
        PointEntryRepository $points,
        ClockInterface $clock,
        #[MapQueryParameter] string $qui = 'moi',
        #[MapQueryParameter] int $jours = 7,
    ): Response {
        $household = 'coloc' === $qui;
        $days = \in_array($jours, self::PERIODS, true) ? $jours : self::PERIODS[0];
        $to = $clock->now()->setTime(0, 0)->modify('+1 day');
        $from = $to->modify(\sprintf('-%d days', $days));

        $done = $household
            ? $completions->findForHousehold($member->getHousehold(), $from, $to)
            : $completions->findForMember($member, $from, $to);

        return $this->render('history/show.html.twig', [
            'history' => new History(
                $household ? null : $member,
                $days,
                $done,
                $points->sumByCompletion($done),
                array_map(static fn (int $i): \DateTimeImmutable => $from->modify(\sprintf('+%d days', $i)), range(0, $days - 1)),
                $household ? $member->getHousehold()->getMembers()->toArray() : [],
            ),
            'periods' => self::PERIODS,
        ]);
    }
}
