<?php

namespace App\Controller;

use App\Entity\Ledger;
use App\Entity\Member;
use App\Entity\Participant;
use App\Form\LedgerType;
use App\Ledger\Currency;
use App\Ledger\ExpenseRecorder;
use App\Ledger\LedgerBook;
use App\Ledger\LedgerExport;
use App\Ledger\Settlement;
use App\Repository\ExpenseRepository;
use App\Repository\MemberRepository;
use App\Security\HouseholdVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * The accounts kept together, like tricounts: the coloc's common expenses, a weekend away. Each shows its expenses,
 * and its balances: where everyone stands, and who reimburses whom — settled from there in one tap.
 */
#[Route('/comptes')]
final class LedgerController extends AbstractController
{
    /** ?vue=equilibres: the account opens on its balances. */
    public const BALANCES = 'equilibres';

    public function __construct(
        private readonly LedgerBook $book,
        private readonly ExpenseRepository $expenses,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @param bool $depense from "+ Une dépense": an account is picked to add it to */
    #[Route('', name: 'ledger_index', methods: ['GET'])]
    public function index(#[CurrentUser] Member $member, #[MapQueryParameter] bool $depense = false): Response
    {
        $ledgers = $this->book->forHousehold($member->getHousehold());
        $balances = $this->expenses->balances($ledgers);

        return $this->render('ledger/index.html.twig', [
            'ledgers' => $ledgers,
            'mine' => array_map(static fn (Ledger $ledger): ?int => null === ($me = $ledger->participantFor($member)) ? null : $balances[(int) $me->getId()] ?? 0, $ledgers),
            'totals' => $this->expenses->totals($ledgers),
            'adding' => $depense,
        ]);
    }

    /** "+ Une dépense", from anywhere: straight to the form when the coloc keeps a single account. */
    #[Route('/depense', name: 'ledger_expense_any', methods: ['GET'])]
    public function expenseAnywhere(#[CurrentUser] Member $member): RedirectResponse
    {
        $ledgers = $this->book->forMember($member) ?: $this->book->forHousehold($member->getHousehold());

        return 1 === \count($ledgers)
            ? $this->redirectToRoute('expense_new', ['id' => $ledgers[0]->getId()])
            : $this->redirectToRoute('ledger_index', ['depense' => 1]);
    }

    /** The whole coloc's account, opened in one tap. */
    #[Route('/coloc', name: 'ledger_open_common', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function openCommon(#[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $ledger = $this->book->openCommon($member->getHousehold());
        $this->addFlash('success', 'Les comptes de la coloc sont ouverts : à la première dépense !');

        return $request->request->getBoolean('_adding')
            ? $this->redirectToRoute('expense_new', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('ledger_show', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/nouveau', name: 'ledger_new', methods: ['GET', 'POST'])]
    public function new(#[CurrentUser] Member $member, Request $request, ClockInterface $clock): Response
    {
        $household = $member->getHousehold();
        $ledger = new Ledger($household, '', $clock->now());
        $form = $this->createForm(LedgerType::class, $ledger, ['household' => $household])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<Member> $members */
            $members = $ledger->isEveryone() ? $household->getMembers()->toArray() : $form->get('members')->getData();
            foreach ($members as $coloc) {
                $ledger->addParticipant($coloc->getName(), $coloc);
            }
            foreach (self::names((string) $form->get('guests')->getData()) as $guest) {
                $ledger->addParticipant($guest);
            }
            if ($ledger->getParticipants()->isEmpty()) {
                $ledger->addParticipant($member->getName(), $member);
            }
            $this->entityManager->persist($ledger);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('« %s » est ouvert.', $ledger->getName()));

            return $this->redirectToRoute('ledger_show', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ledger/new.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * The account in two tabs: its expenses day by day, the latest first, with what was spent in all and for me; and
     * its balances, where everyone stands and the fewest reimbursements for everyone to be even, each settled in one
     * tap. ?vue=equilibres opens the second.
     */
    #[Route('/{id}', name: 'ledger_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    public function show(Ledger $ledger, #[CurrentUser] Member $member, #[MapQueryParameter] ?string $vue = null): Response
    {
        $this->book->welcome($ledger);
        $me = $ledger->participantFor($member);
        $expenses = $this->expenses->findForLedger($ledger);
        $days = [];
        // What each one paid, and what was spent for them: transfers aside, incomes taken off.
        $paid = [];
        $spent = [];
        foreach ($expenses as $expense) {
            $days[$expense->getSpentOn()->format('Y-m-d')][] = $expense;
            if (!$expense->getKind()->isShared()) {
                continue;
            }
            $sign = $expense->getKind()->sign();
            $payer = (int) $expense->getPaidBy()->getId();
            $paid[$payer] = ($paid[$payer] ?? 0) + $sign * $expense->getConvertedAmount();
            foreach ($expense->getShares() as $share) {
                $id = (int) $share->getParticipant()->getId();
                $spent[$id] = ($spent[$id] ?? 0) + $sign * $share->getConvertedAmount();
            }
        }

        return $this->render('ledger/show.html.twig', [
            'ledger' => $ledger,
            'me' => $me,
            'tab' => self::BALANCES === $vue ? 'balances' : 'expenses',
            'days' => $days,
            'count' => \count($expenses),
            'total' => array_sum($paid),
            'my_total' => null === $me ? 0 : $spent[(int) $me->getId()] ?? 0,
            'paid' => $paid,
            'spent' => $spent,
            'sheet' => $this->book->sheet($ledger),
        ]);
    }

    /**
     * "Régler": the reimbursement suggested by the balances, handed over today. Already settled meanwhile (by someone
     * else, or a second tap), nothing more is noted; never more than what is still owed.
     */
    #[Route('/{id}/regler', name: 'ledger_settle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function settle(Ledger $ledger, #[CurrentUser] Member $member, Request $request, ExpenseRecorder $recorder): RedirectResponse
    {
        $participants = $ledger->participantsById();
        $from = $participants[$request->request->getInt('from')] ?? null;
        $to = $participants[$request->request->getInt('to')] ?? null;
        $settlement = null === $from || null === $to ? null : $this->book->sheet($ledger)->settlementBetween($from, $to);

        if (null === $settlement) {
            $this->addFlash('success', 'C’était déjà réglé.');
        } else {
            $amount = min($settlement->amount, max(1, $request->request->getInt('amount', $settlement->amount)));
            $recorder->settle(new Settlement($settlement->from, $settlement->to, $amount), $member);
            $this->addFlash('success', 'C’est réglé : '.self::reimbursement($settlement->from, $settlement->to, Currency::format($amount, $ledger->getCurrency()), $member).'.');
        }

        return $this->redirectToRoute('ledger_show', ['id' => $ledger->getId(), 'vue' => self::BALANCES], Response::HTTP_SEE_OTHER);
    }

    /** Its name, its currency, who is in it; and closing it. */
    #[Route('/{id}/reglages', name: 'ledger_settings', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    public function settings(Ledger $ledger, #[CurrentUser] Member $member, Request $request): Response
    {
        $this->book->welcome($ledger);
        $count = $this->expenses->countForLedger($ledger);
        $form = $this->createForm(LedgerType::class, $ledger, ['currency_locked' => $count > 0])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->book->welcome($ledger, false);
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute('ledger_settings', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER);
        }
        if ($form->isSubmitted()) {
            // Its name as it is kept, above the form that says what was wrong.
            $this->entityManager->refresh($ledger);
        }

        $household = $member->getHousehold();
        $involved = [];
        foreach ($ledger->getParticipants() as $participant) {
            $involved[(int) $participant->getId()] = $this->expenses->involves($participant);
        }

        return $this->render('ledger/settings.html.twig', [
            'ledger' => $ledger,
            'form' => $form,
            'count' => $count,
            'involved' => $involved,
            'outside' => array_values(array_filter($household->getMembers()->toArray(), static fn (Member $coloc): bool => null === $ledger->participantFor($coloc))),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** A coloc who was not in it, or a guest known by their name. */
    #[Route('/{id}/participants', name: 'ledger_participant_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function addParticipant(Ledger $ledger, Request $request, MemberRepository $members): RedirectResponse
    {
        $coloc = $request->request->has('member') ? $members->find($request->request->getInt('member')) : null;
        if (null !== $coloc && $coloc->belongsTo($ledger->getHousehold())) {
            if (null === $ledger->participantFor($coloc)) {
                $ledger->addParticipant($coloc->getName(), $coloc);
                $this->entityManager->flush();
                $this->addFlash('success', \sprintf('%s est dans le compte.', $coloc->getName()));
            }
        } else {
            $name = mb_substr(self::names($request->request->getString('name'))[0] ?? '', 0, Participant::NAME_MAX_LENGTH);
            if ('' !== $name) {
                $ledger->addParticipant($name);
                $this->entityManager->flush();
                $this->addFlash('success', \sprintf('%s est dans le compte.', $name));
            }
        }

        return $this->redirectToRoute('ledger_settings', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER);
    }

    /** A guest's name, put right. */
    #[Route('/{id}/participants/{participant}', name: 'ledger_participant_rename', requirements: ['id' => '\d+', 'participant' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function renameParticipant(Ledger $ledger, #[MapEntity(id: 'participant')] Participant $participant, Request $request): RedirectResponse
    {
        $this->assertIn($ledger, $participant);
        $name = mb_substr(self::names($request->request->getString('name'))[0] ?? '', 0, Participant::NAME_MAX_LENGTH);
        if ($participant->isGuest() && '' !== $name) {
            $participant->setName($name);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('C’est %s, maintenant.', $name));
        }

        return $this->redirectToRoute('ledger_settings', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER);
    }

    /** Someone who has nothing in it yet: whoever paid or had a share of something stays. */
    #[Route('/{id}/participants/{participant}/retirer', name: 'ledger_participant_remove', requirements: ['id' => '\d+', 'participant' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function removeParticipant(Ledger $ledger, #[MapEntity(id: 'participant')] Participant $participant): RedirectResponse
    {
        $this->assertIn($ledger, $participant);
        if ($this->expenses->involves($participant)) {
            $this->addFlash('success', \sprintf('%s a des dépenses dans ce compte : %s reste.', $participant->getName(), $participant->isGuest() ? 'cette personne' : 'ce coloc'));
        } elseif ($ledger->isEveryone() && !$participant->isGuest()) {
            $this->addFlash('success', 'Toute la coloc est dans ce compte : décoche « Toute la coloc » d’abord.');
        } else {
            $ledger->removeParticipant($participant);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('%s n’est plus dans le compte.', $participant->getName()));
        }

        return $this->redirectToRoute('ledger_settings', ['id' => $ledger->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/supprimer', name: 'ledger_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function delete(Ledger $ledger): RedirectResponse
    {
        $name = $ledger->getName();
        $this->book->delete($ledger);
        $this->addFlash('success', \sprintf('« %s » est fermé.', $name));

        return $this->redirectToRoute('ledger_index', status: Response::HTTP_SEE_OTHER);
    }

    /** Every line in a spreadsheet: what, when, who paid, and each one's share. */
    #[Route('/{id}/export', name: 'ledger_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    public function export(Ledger $ledger, LedgerExport $export): Response
    {
        $response = new Response($export->csv($ledger), Response::HTTP_OK, ['Content-Type' => 'text/csv; charset=UTF-8']);
        $file = strtolower((new AsciiSlugger('fr'))->slug($ledger->getName())->toString()) ?: 'compte';
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file.'.csv'));

        return $response;
    }

    /** "Robin a remboursé 23,40 € à Léo", "tu as remboursé…", "Robin t’a remboursé…". */
    public static function reimbursement(Participant $from, Participant $to, string $amount, Member $member): string
    {
        return match ($member) {
            $from->getMember() => \sprintf('tu as remboursé %s à %s', $amount, $to->getName()),
            $to->getMember() => \sprintf('%s t’a remboursé %s', $from->getName(), $amount),
            default => \sprintf('%s a remboursé %s à %s', $from->getName(), $amount, $to->getName()),
        };
    }

    /** @return list<string> "Camille\n  Max " is Camille and Max */
    private static function names(string $typed): array
    {
        return array_values(array_filter(array_map(
            static fn (string $name): string => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $name)), 0, Participant::NAME_MAX_LENGTH),
            preg_split('/[\r\n,]+/', $typed) ?: [],
        ), static fn (string $name): bool => '' !== $name));
    }

    private function assertIn(Ledger $ledger, Participant $participant): void
    {
        if ($participant->getLedger() !== $ledger) {
            throw $this->createNotFoundException();
        }
    }
}
