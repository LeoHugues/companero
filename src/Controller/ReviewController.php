<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\Member;
use App\Points\PointAdjuster;
use App\Review\WeeklyReviewBuilder;
use App\Security\HouseholdVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReviewController extends AbstractController
{
    #[Route('/bilan/{week}', name: 'review_show', requirements: ['week' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    public function show(#[CurrentUser] Member $member, WeeklyReviewBuilder $reviews, ClockInterface $clock, ?string $week = null): Response
    {
        $now = $clock->now();
        $selected = Week::containing(null !== $week ? new \DateTimeImmutable($week) : $now);
        $household = $member->getHousehold();

        return $this->render('review/show.html.twig', [
            'review' => $reviews->build($household, $selected),
            'has_next' => $selected->end() <= $now,
            'has_previous' => $selected->start > Week::containing($household->getCreatedAt())->start,
            'cleaning_day' => $household->getCleaningDay(),
        ]);
    }

    #[Route('/realisations/{id}/ajuster', name: 'completion_adjust', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'completion')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function adjust(Completion $completion, #[CurrentUser] Member $member, Request $request, PointAdjuster $adjuster): RedirectResponse
    {
        $adjuster->adjust($completion, $member, 'plus' === $request->request->getString('sens') ? PointAdjuster::STEP : -PointAdjuster::STEP);
        $this->addFlash('success', 'Merci, c’est noté pour '.$completion->getMember()->getName().'.');

        return $this->redirectToRoute('review_show', [
            'week' => Week::containing($completion->getCompletedAt())->start->format('Y-m-d'),
        ], Response::HTTP_SEE_OTHER);
    }
}
