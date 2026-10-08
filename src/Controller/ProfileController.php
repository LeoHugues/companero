<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Member;
use App\Form\ProfileType;
use App\Presence\PresenceRecorder;
use App\Progress\LevelProvider;
use App\Repository\EarnedTitleRepository;
use App\Repository\PresenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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
        PresenceRepository $presences,
        PresenceRecorder $presenceRecorder,
        EarnedTitleRepository $titles,
        ClockInterface $clock,
    ): Response {
        $profileForm = $this->createForm(ProfileType::class, $member);
        $profileForm->get('presenceDays')->setData($presences->daysOf($member, Week::containing($clock->now())));
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $presenceRecorder->declareDays($member, (int) $profileForm->get('presenceDays')->getData());
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute('profile_show', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/show.html.twig', [
            'member' => $member,
            'level' => $levels->levelOf($member),
            'profile_form' => $profileForm,
            'titles' => $titles->findForMember($member),
        ], new Response(status: $profileForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
