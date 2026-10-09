<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Household;
use App\Entity\Member;
use App\Form\TourSettingsType;
use App\Presence\PresenceRecorder;
use App\Repository\PresenceRepository;
use App\Review\WeekCloser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * The tour, told by the Casa: what the app is for, the cards and how they glide, the points and the
 * two goals (A), then the charter and one's own settings (B). It must be finished before anything
 * else (RequireOnboardingListener); its explanations can be read again from the profile.
 */
#[Route('/decouverte')]
final class TourController extends AbstractController
{
    /** The explanations, in order; then come the charter and the settings. */
    public const STEPS = ['esprit', 'carte', 'glissement', 'rythmes', 'points', 'objectifs'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'tour_start', methods: ['GET'])]
    public function start(): RedirectResponse
    {
        return $this->redirectToRoute('tour_step', ['step' => self::STEPS[0]]);
    }

    #[Route('/{step}', name: 'tour_step', requirements: ['step' => 'esprit|carte|glissement|rythmes|points|objectifs'], methods: ['GET'])]
    public function step(string $step, #[CurrentUser] Member $member): Response
    {
        $index = (int) array_search($step, self::STEPS, true);
        $household = $member->getHousehold();

        return $this->render(\sprintf('onboarding/tour/%s.html.twig', $step), [
            'step' => $step,
            'index' => $index,
            'replay' => $member->isOnboarded(),
            'previous' => $index > 0 ? $this->generateUrl('tour_step', ['step' => self::STEPS[$index - 1]]) : null,
            'next' => match (true) {
                isset(self::STEPS[$index + 1]) => $this->generateUrl('tour_step', ['step' => self::STEPS[$index + 1]]),
                $member->isOnboarded() => $this->generateUrl('profile_show'),
                default => $this->generateUrl('tour_charter'),
            },
            'household' => $household,
            'team_xp' => WeekCloser::TEAM_XP,
            'share' => $this->share($household),
        ]);
    }

    #[Route('/charte', name: 'tour_charter', methods: ['GET'])]
    public function charter(#[CurrentUser] Member $member): Response
    {
        if ($member->isOnboarded()) {
            return $this->redirectToRoute('charter_show');
        }

        return $this->render('onboarding/tour/charte.html.twig', [
            'step' => 'charte',
            'index' => \count(self::STEPS),
            'replay' => false,
            'previous' => $this->generateUrl('tour_step', ['step' => self::STEPS[\count(self::STEPS) - 1]]),
            'household' => $member->getHousehold(),
        ]);
    }

    #[Route('/charte', name: 'tour_charter_accept', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function acceptCharter(#[CurrentUser] Member $member): RedirectResponse
    {
        $member->acceptCharter($this->clock->now());
        $this->entityManager->flush();

        return $this->redirectToRoute($member->isOnboarded() ? 'app_home' : 'tour_settings', status: Response::HTTP_SEE_OTHER);
    }

    /** One's own goal, days at home and reminders: the goal is chosen, never left as it was. */
    #[Route('/reglages', name: 'tour_settings', methods: ['GET', 'POST'])]
    public function settings(#[CurrentUser] Member $member, Request $request, PresenceRepository $presences, PresenceRecorder $presenceRecorder): Response
    {
        if ($member->isOnboarded()) {
            return $this->redirectToRoute('profile_show');
        }
        if (!$member->hasAcceptedCharter()) {
            return $this->redirectToRoute('tour_charter');
        }

        $form = $this->createForm(TourSettingsType::class, $member);
        $form->get('presenceDays')->setData($presences->daysOf($member, Week::containing($this->clock->now())));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setWeeklyGoal((int) $form->get('goal')->getData());
            $presenceRecorder->declareDays($member, (int) $form->get('presenceDays')->getData());
            $member->finishOnboarding($this->clock->now());
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('C’est parti, %s ! La Casa compte sur toi.', $member->getName()));

            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('onboarding/tour/reglages.html.twig', [
            'step' => 'reglages',
            'index' => \count(self::STEPS) + 1,
            'replay' => false,
            'previous' => $this->generateUrl('tour_charter'),
            'form' => $form,
            'household' => $member->getHousehold(),
            'share' => $this->share($member->getHousehold()),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** What the household's goal asks of each of us, give or take. */
    private function share(Household $household): int
    {
        return (int) (round($household->getWeeklyGoal() / max(1, $household->getMembers()->count()) / 10) * 10);
    }
}
