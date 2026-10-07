<?php

namespace App\Controller;

use App\Entity\Household;
use App\Form\FoundingType;
use App\Form\RegistrationType;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OnboardingController extends AbstractController
{
    #[Route('/bienvenue', name: 'onboarding_found', methods: ['GET', 'POST'])]
    public function found(Request $request, HouseholdFounder $founder, Security $security): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $founding = new Founding();
        $form = $this->createForm(FoundingType::class, $founding)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $security->login($founder->found($founding), 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('onboarding/found.html.twig', ['form' => $form]);
    }

    #[Route('/rejoindre/{token}', name: 'onboarding_join', methods: ['GET', 'POST'])]
    public function join(
        #[MapEntity(mapping: ['token' => 'inviteToken'])] Household $household,
        Request $request,
        MemberRegistrar $registrar,
        Security $security,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $registration = new Registration();
        $form = $this->createForm(RegistrationType::class, $registration)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $security->login($registrar->register($household, $registration), 'form_login', 'main');
            $this->addFlash('success', \sprintf('Bienvenue dans %s !', $household->getName()));

            return $this->redirectToRoute('app_home');
        }

        return $this->render('onboarding/join.html.twig', ['form' => $form, 'household' => $household]);
    }
}
