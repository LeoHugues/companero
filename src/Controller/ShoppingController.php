<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\ShoppingItem;
use App\Form\ShoppingItemType;
use App\Repository\ShoppingItemRepository;
use App\Security\HouseholdVoter;
use App\Shopping\ShoppingListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * What the house is missing: anyone notices it in a few letters (or one tap on a suggestion), anyone ticks it once
 * bought. Each form comes back to the list, refreshed in place: the field empties and keeps the keyboard, for the next one.
 */
#[Route('/courses')]
final class ShoppingController extends AbstractController
{
    public function __construct(
        private readonly ShoppingItemRepository $items,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @param bool $ajouter from a "+": the field has the focus */
    #[Route('', name: 'shopping_index', methods: ['GET'])]
    public function index(#[CurrentUser] Member $member, ShoppingListBuilder $lists, #[MapQueryParameter] bool $ajouter = false): Response
    {
        return $this->render('shopping/index.html.twig', [
            'list' => $lists->build($member->getHousehold()),
            'adding' => $ajouter,
        ]);
    }

    /** "Il manque…": a name typed, or a suggestion tapped. Already on the list, it is not written twice. */
    #[Route('', name: 'shopping_add', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function add(#[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $name = mb_substr(ShoppingItem::tidy($request->request->getString('name')), 0, ShoppingItem::NAME_MAX_LENGTH);
        if ('' !== $name) {
            $household = $member->getHousehold();
            $item = $this->items->findOneByName($household, $name) ?? new ShoppingItem($household, $name);
            $wasUrgent = $item->isUrgent();
            $added = $item->notice($member, $this->clock->now(), $request->request->getBoolean('urgent'));
            $this->entityManager->persist($item);
            $this->entityManager->flush();
            $this->addFlash('success', match (true) {
                $added => \sprintf('C’est noté : %s.', self::inSentence($item->getName())),
                $item->isUrgent() && !$wasUrgent => \sprintf('%s était déjà sur la liste : c’est urgent, maintenant.', $item->getName()),
                default => \sprintf('%s est déjà sur la liste.', $item->getName()),
            });
        }

        return $this->backToList($request);
    }

    #[Route('/{id}/achete', name: 'shopping_buy', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'item')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function buy(ShoppingItem $item, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        if ($item->isMissing()) {
            $item->buy($member, $this->clock->now());
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('Merci %s ! %s : c’est réglé.', $member->getName(), $item->getName()));
        }

        return $this->backToList($request);
    }

    /** Bought a little too soon (a slip of the finger), or missing again already. */
    #[Route('/{id}/remettre', name: 'shopping_again', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'item')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function again(ShoppingItem $item, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        if ($item->notice($member, $this->clock->now())) {
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('Remis sur la liste : %s.', self::inSentence($item->getName())));
        }

        return $this->backToList($request);
    }

    #[Route('/{id}', name: 'shopping_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'item')]
    public function edit(ShoppingItem $item, Request $request): Response
    {
        $form = $this->createForm(ShoppingItemType::class, $item)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('shopping_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('shopping/edit.html.twig', ['item' => $item, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Not needed after all. Once bought before, it stays among the suggestions; a slip of the keyboard is forgotten. */
    #[Route('/{id}/retirer', name: 'shopping_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'item')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function remove(ShoppingItem $item): RedirectResponse
    {
        if ($item->wasEverBought()) {
            $item->forget();
        } else {
            $this->entityManager->remove($item);
        }
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('Retiré de la liste : %s.', self::inSentence($item->getName())));

        return $this->redirectToRoute('shopping_index', status: Response::HTTP_SEE_OTHER);
    }

    /** Back where the form was: the list, with the field ready for the next one if it was opened to add. */
    private function backToList(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('shopping_index', $request->request->getBoolean('_adding') ? ['ajouter' => 1] : [], Response::HTTP_SEE_OTHER);
    }

    /** "papier toilette" within a sentence, but "PQ" stays "PQ". */
    private static function inSentence(string $name): string
    {
        $first = explode(' ', $name)[0];

        return mb_strlen($first) > 1 && mb_strtoupper($first) === $first ? $name : mb_lcfirst($name);
    }
}
