<?php

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\Member;
use App\Form\AbsenceType;
use App\Form\ProfileType;
use App\Progress\LevelProvider;
use App\Repository\AbsenceRepository;
use App\Repository\EarnedTitleRepository;
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

#[Route('/profil')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'profile_show', methods: ['GET', 'POST'])]
    public function show(
        #[CurrentUser] Member $member,
        Request $request,
        LevelProvider $levels,
        AbsenceRepository $absences,
        EarnedTitleRepository $titles,
        ClockInterface $clock,
    ): Response {
        $profileForm = $this->createForm(ProfileType::class, $member)->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute('profile_show', status: Response::HTTP_SEE_OTHER);
        }

        $absence = new Absence($member);
        $absenceForm = $this->createForm(AbsenceType::class, $absence)->handleRequest($request);
        if ($absenceForm->isSubmitted() && $absenceForm->isValid()) {
            $this->entityManager->persist($absence);
            $this->entityManager->flush();
            $this->addFlash('success', 'Absence notée : tes objectifs sont ajustés.');

            return $this->redirectToRoute('profile_show', status: Response::HTTP_SEE_OTHER);
        }

        $status = $profileForm->isSubmitted() || $absenceForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('profile/show.html.twig', [
            'member' => $member,
            'level' => $levels->levelOf($member),
            'profile_form' => $profileForm,
            'absence_form' => $absenceForm,
            'absences' => $absences->findCurrentAndUpcoming($member, $clock->now()),
            'titles' => $titles->findForMember($member),
        ], new Response(status: $status));
    }

    #[Route('/absences/{id}/supprimer', name: 'absence_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'absence')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function deleteAbsence(Absence $absence): RedirectResponse
    {
        $this->entityManager->remove($absence);
        $this->entityManager->flush();

        return $this->redirectToRoute('profile_show', status: Response::HTTP_SEE_OTHER);
    }
}
