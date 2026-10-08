<?php

namespace App\Controller;

use App\Entity\Gift;
use App\Entity\Member;
use App\Entity\Pet;
use App\Enum\GiftKind;
use App\Repository\MemberRepository;
use App\Reward\GiftUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/** Opening the gifts of the profile: activating a boost, offering a freeze, giving a treat. */
#[Route('/cadeaux/{id}', requirements: ['id' => '\d+'])]
final class GiftController extends AbstractController
{
    public function __construct(
        private readonly GiftUser $giftUser,
        private readonly MemberRepository $members,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/activer', name: 'gift_activate', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function activate(Gift $gift, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $this->assertOwnedBy($gift, $member);
        $friend = GiftKind::FriendBoost === $gift->getKind() ? $this->friend($member, $request) : null;

        try {
            $this->giftUser->activate($gift, $friend);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $this->addFlash('success', match ($gift->getKind()) {
            GiftKind::TeamBoost => 'Boost activé : toute la coloc gagne +1 pt tous les 3 pts pendant 24 h !',
            GiftKind::FriendBoost => \sprintf('Boost offert à %s pour 24 h !', $friend?->getName()),
            default => 'Boost d’XP activé pour 24 h !',
        });

        return $this->back();
    }

    #[Route('/offrir', name: 'gift_offer', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function offer(Gift $gift, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $this->assertOwnedBy($gift, $member);
        $friend = $this->friend($member, $request);

        try {
            $this->giftUser->offer($gift, $friend);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
        $this->addFlash('success', \sprintf('Gel de série offert à %s. Sympa !', $friend->getName()));

        return $this->back();
    }

    #[Route('/carton', name: 'gift_card', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function card(Gift $gift, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $this->assertOwnedBy($gift, $member);
        $friend = $this->friend($member, $request);
        $reason = $request->request->getString('motif');
        if ('' === trim($reason)) {
            $this->addFlash('success', 'Un carton, c’est pour quelque chose : dis pour quoi.');

            return $this->back();
        }

        try {
            $this->giftUser->giveCard($gift, $friend, $reason);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
        $this->addFlash('card_given', ['to' => $friend->getName(), 'reason' => trim($reason)]);

        return $this->back();
    }

    #[Route('/donner', name: 'gift_treat', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function treat(Gift $gift, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $this->assertOwnedBy($gift, $member);
        $petId = $request->request->getString('animal');
        $pet = '' === $petId ? null : $this->entityManager->find(Pet::class, (int) $petId);
        if ('' !== $petId && null === $pet) {
            throw new BadRequestHttpException('Unknown pet.');
        }

        try {
            $this->giftUser->treat($gift, $pet);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
        $this->addFlash('success', null !== $pet ? \sprintf('%s se régale. Ronron !', $pet->getName()) : 'Friandise partagée : tout le monde se régale !');

        return $this->back();
    }

    private function assertOwnedBy(Gift $gift, Member $member): void
    {
        if ($gift->getOwner() !== $member) {
            throw $this->createAccessDeniedException();
        }
    }

    private function friend(Member $member, Request $request): Member
    {
        $friend = $this->members->find($request->request->getInt('pour'));
        if (null === $friend || $friend === $member || !$friend->belongsTo($member->getHousehold())) {
            throw new BadRequestHttpException('Choose another member of the household.');
        }

        return $friend;
    }

    private function back(): RedirectResponse
    {
        return $this->redirect($this->generateUrl('profile_show').'#gifts-title', Response::HTTP_SEE_OTHER);
    }
}
