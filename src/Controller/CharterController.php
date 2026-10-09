<?php

namespace App\Controller;

use App\Entity\CharterRule;
use App\Entity\Member;
use App\Form\CharterRuleType;
use App\Security\HouseholdVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The household's charter: common sense for living together. Everyone may write it; each member
 * agrees to it ("Ça me va"), and again once it has changed.
 */
#[Route('/coloc/charte')]
final class CharterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'charter_show', methods: ['GET', 'POST'])]
    public function show(#[CurrentUser] Member $member, Request $request): Response
    {
        $household = $member->getHousehold();
        $form = $this->createForm(CharterRuleType::class, options: ['household' => $household])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $household->touchCharter($member, $this->clock->now());
            $this->entityManager->flush();

            return $this->redirectToRoute('charter_show', status: Response::HTTP_SEE_OTHER);
        }
        if ($form->isSubmitted()) {
            // The form's empty data already joined the charter: it must not be shown as one of its rules.
            $household->removeCharterRule($form->getData());
        }

        return $this->render('charter/show.html.twig', [
            'household' => $household,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/adherer', name: 'charter_accept', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function accept(#[CurrentUser] Member $member): RedirectResponse
    {
        $member->acceptCharter($this->clock->now());
        $this->entityManager->flush();
        $this->addFlash('success', 'Merci ! C’est noté.');

        return $this->redirectToRoute('charter_show', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'charter_rule_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'rule')]
    public function edit(CharterRule $rule, #[CurrentUser] Member $member, Request $request): Response
    {
        $form = $this->createForm(CharterRuleType::class, $rule, ['household' => $rule->getHousehold(), 'new' => false])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $rule->getHousehold()->touchCharter($member, $this->clock->now());
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré : chacun est invité à relire la charte.');

            return $this->redirectToRoute('charter_show', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('charter/rule.html.twig', ['rule' => $rule, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/supprimer', name: 'charter_rule_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'rule')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function delete(CharterRule $rule, #[CurrentUser] Member $member): RedirectResponse
    {
        $household = $rule->getHousehold();
        $household->removeCharterRule($rule);
        $household->touchCharter($member, $this->clock->now());
        $this->entityManager->flush();

        return $this->redirectToRoute('charter_show', status: Response::HTTP_SEE_OTHER);
    }

    /** Up or down a rank: the words do not change, nobody needs to read it again. */
    #[Route('/{id}/deplacer', name: 'charter_rule_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'rule')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function move(CharterRule $rule, Request $request): RedirectResponse
    {
        $rule->getHousehold()->moveCharterRule($rule, 'haut' === $request->request->getString('sens') ? -1 : 1);
        $this->entityManager->flush();

        return $this->redirectToRoute('charter_show', status: Response::HTTP_SEE_OTHER);
    }
}
