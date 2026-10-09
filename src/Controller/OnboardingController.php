<?php

namespace App\Controller;

use App\Entity\Household;
use App\Entity\Member;
use App\Form\FoundingType;
use App\Form\RegistrationType;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

/** Creating the household, joining it, or claiming the profile waiting there; then the tour (TourController). */
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    #[Route('/bienvenue', name: 'onboarding_found', methods: ['GET', 'POST'])]
    public function found(Request $request, HouseholdFounder $founder): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $founding = new Founding();
        $form = $this->createForm(FoundingType::class, $founding)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->logIn($founder->found($founding));
        }

        return $this->render('onboarding/found.html.twig', ['form' => $form]);
    }

    /** "Qui es-tu ?": the profiles waiting to be claimed, or someone new (?nouveau). */
    #[Route('/rejoindre/{token}', name: 'onboarding_join', methods: ['GET', 'POST'])]
    public function join(
        #[MapEntity(mapping: ['token' => 'inviteToken'])] Household $household,
        Request $request,
        MemberRegistrar $registrar,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $unclaimed = $household->getUnclaimedMembers();
        if ([] !== $unclaimed && $request->isMethod('GET') && !$request->query->has('nouveau')) {
            return $this->render('onboarding/join_choose.html.twig', ['household' => $household, 'unclaimed' => $unclaimed]);
        }

        $registration = new Registration();
        $form = $this->createForm(RegistrationType::class, $registration)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', \sprintf('Bienvenue dans %s !', $household->getName()));

            return $this->logIn($registrar->register($household, $registration));
        }

        return $this->render('onboarding/join.html.twig', ['form' => $form, 'household' => $household, 'can_choose' => [] !== $unclaimed]);
    }

    /** "Je suis Robin": the profile waiting for them, with everything already attached to it. */
    #[Route('/rejoindre/{token}/profil/{member}', name: 'onboarding_claim', requirements: ['member' => '\d+'], methods: ['GET', 'POST'])]
    public function claim(
        #[MapEntity(mapping: ['token' => 'inviteToken'])] Household $household,
        #[MapEntity(id: 'member')] Member $member,
        Request $request,
        MemberRegistrar $registrar,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        if (!$member->belongsTo($household)) {
            throw $this->createNotFoundException();
        }
        if ($member->isClaimed()) {
            $this->addFlash('success', \sprintf('Le profil de %s a déjà été réclamé : connecte-toi avec son pseudo.', $member->getName()));

            return $this->redirectToRoute('app_login');
        }

        $registration = new Registration();
        $registration->name = $member->getName();
        $registration->username = $member->getName();
        $form = $this->createForm(RegistrationType::class, $registration, ['with_name' => false])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', \sprintf('Salut %s ! Tout ce qui était à toi t’attendait.', $member->getName()));

            return $this->logIn($registrar->claim($member, $registration));
        }

        return $this->render('onboarding/claim.html.twig', ['form' => $form, 'household' => $household, 'member' => $member]);
    }

    /** Logged in for a long time, as with the login form (it lives on the phone), then off to the tour. */
    private function logIn(Member $member): RedirectResponse
    {
        $this->security->login($member, 'form_login', 'main', [new RememberMeBadge()]);

        return $this->redirectToRoute('tour_start');
    }
}
