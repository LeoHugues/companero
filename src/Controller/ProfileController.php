<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Gift;
use App\Entity\Member;
use App\Enum\GiftKind;
use App\Form\ProfileType;
use App\Presence\PresenceRecorder;
use App\Progress\LevelProvider;
use App\Progress\TeamProgressBuilder;
use App\Repository\BountyRepository;
use App\Repository\CompletionRepository;
use App\Repository\EarnedTitleRepository;
use App\Repository\GiftRepository;
use App\Repository\PresenceRepository;
use App\Repository\YellowCardRepository;
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
        GiftRepository $gifts,
        CompletionRepository $completions,
        BountyRepository $bounties,
        YellowCardRepository $cards,
        TeamProgressBuilder $teamProgress,
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
            'week' => $teamProgress->build($member->getHousehold(), Week::containing($clock->now()))->of($member),
            'stats' => [
                'done' => $completions->count(['member' => $member]),
                'surprises' => $bounties->countClaimedBy($member),
                'cards' => $cards->count(['givenTo' => $member]),
            ],
            'profile_form' => $profileForm,
            'titles' => $titles->findForMember($member),
            'gifts' => $this->groupByKind($gifts->findUnused($member)),
            'friends' => array_values(array_filter($member->getHousehold()->getMembers()->toArray(), static fn (Member $other): bool => $other !== $member)),
        ], new Response(status: $profileForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param list<Gift> $gifts
     *
     * @return list<array{kind: GiftKind, gifts: non-empty-list<Gift>}> the oldest gift of each kind is used first
     */
    private function groupByKind(array $gifts): array
    {
        $groups = [];
        foreach (GiftKind::cases() as $kind) {
            $ofKind = array_values(array_filter($gifts, static fn (Gift $gift): bool => $gift->getKind() === $kind));
            if ([] !== $ofKind) {
                $groups[] = ['kind' => $kind, 'gifts' => $ofKind];
            }
        }

        return $groups;
    }
}
