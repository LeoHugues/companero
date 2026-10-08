<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\Zone;
use App\Repository\CompletionRepository;
use App\Security\HouseholdVoter;
use App\Task\TaskBoardBuilder;
use App\Task\ZoneSummary;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The house seen from above: one tile per room, and what each room needs. */
#[Route('/plan')]
final class PlanController extends AbstractController
{
    private const HISTORY_DAYS = 30;

    #[Route('', name: 'plan_show', methods: ['GET'])]
    public function show(#[CurrentUser] Member $member, TaskBoardBuilder $boards): Response
    {
        $household = $member->getHousehold();

        return $this->render('plan/show.html.twig', [
            'rooms' => $boards->build($household)->byZone($household->getZones()),
        ]);
    }

    #[Route('/{id}', name: 'plan_zone', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'zone')]
    public function zone(Zone $zone, TaskBoardBuilder $boards, CompletionRepository $completions, ClockInterface $clock): Response
    {
        $room = $boards->build($zone->getHousehold())->byZone([$zone])[0] ?? new ZoneSummary($zone, []);

        return $this->render('plan/zone.html.twig', [
            'room' => $room,
            'done' => $completions->findRecentForZone($zone, $clock->now()->modify(\sprintf('-%d days', self::HISTORY_DAYS))),
        ]);
    }
}
