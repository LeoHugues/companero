<?php

namespace App\Controller;

use App\Entity\CatalogItem;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Form\HouseholdType;
use App\Form\PetType;
use App\Form\ZoneType;
use App\Repository\GiftRepository;
use App\Security\HouseholdVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/coloc')]
final class HouseholdController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'household_edit', methods: ['GET', 'POST'])]
    public function edit(#[CurrentUser] Member $member, Request $request, GiftRepository $gifts): Response
    {
        $household = $member->getHousehold();

        $householdForm = $this->createForm(HouseholdType::class, $household)->handleRequest($request);
        if ($householdForm->isSubmitted() && $householdForm->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
        }

        $zoneForm = $this->createForm(ZoneType::class, options: ['household' => $household])->handleRequest($request);
        if ($zoneForm->isSubmitted() && $zoneForm->isValid()) {
            $this->entityManager->persist($zoneForm->getData());
            $this->entityManager->flush();

            return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
        }

        $petForm = $this->createForm(PetType::class, options: ['household' => $household])->handleRequest($request);
        if ($petForm->isSubmitted() && $petForm->isValid()) {
            $this->entityManager->persist($petForm->getData());
            $this->entityManager->flush();

            return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
        }
        if ($petForm->isSubmitted()) {
            // The form's empty data already joined the household: it must not be shown as one of its pets.
            $household->removePet($petForm->getData());
        }

        $status = $householdForm->isSubmitted() || $zoneForm->isSubmitted() || $petForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('household/edit.html.twig', [
            'household' => $household,
            'household_form' => $householdForm,
            'zone_form' => $zoneForm,
            'pet_form' => $petForm,
            'treats' => $gifts->findTreatsGiven($household),
        ], new Response(status: $status));
    }

    #[Route('/zones/{id}', name: 'zone_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'zone')]
    public function editZone(Zone $zone, Request $request): Response
    {
        $form = $this->createForm(ZoneType::class, $zone, ['household' => $zone->getHousehold(), 'with_plan' => true])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('household/zone.html.twig', ['zone' => $zone, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/zones/{id}/supprimer', name: 'zone_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'zone')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function deleteZone(Zone $zone): RedirectResponse
    {
        // Its tasks are kept and become "toute la coloc".
        $zone->getHousehold()->removeZone($zone);
        $this->entityManager->flush();

        return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
    }

    /** One room is part of another after all (the entrance, of the living room): its tasks move there. */
    #[Route('/zones/{id}/fusionner', name: 'zone_merge', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'zone')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function mergeZone(Zone $zone, Request $request): RedirectResponse
    {
        $into = $this->entityManager->find(Zone::class, $request->request->getInt('dans'));
        if (null === $into || $into === $zone || $into->getHousehold() !== $zone->getHousehold()) {
            throw $this->createNotFoundException();
        }
        foreach ([Task::class, CatalogItem::class] as $class) {
            $this->entityManager->createQueryBuilder()
                ->update($class, 'x')
                ->set('x.zone', ':into')
                ->where('x.zone = :zone')
                ->setParameter('into', $into)
                ->setParameter('zone', $zone)
                ->getQuery()
                ->execute();
        }
        $zone->getHousehold()->removeZone($zone);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('« %s » fait maintenant partie de « %s », avec ses tâches.', $zone->getName(), $into->getName()));

        return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/animaux/{id}/supprimer', name: 'pet_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'pet')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function deletePet(Pet $pet): RedirectResponse
    {
        // Its tasks are kept, for everyone.
        $pet->getHousehold()->removePet($pet);
        $this->entityManager->flush();

        return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/invitation', name: 'household_invite_reset', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function resetInvitation(#[CurrentUser] Member $member): RedirectResponse
    {
        $member->getHousehold()->regenerateInviteToken();
        $this->entityManager->flush();
        $this->addFlash('success', 'Nouveau lien d’invitation créé : l’ancien ne marche plus.');

        return $this->redirectToRoute('household_edit', status: Response::HTTP_SEE_OTHER);
    }
}
