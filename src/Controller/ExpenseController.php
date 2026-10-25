<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\Ledger;
use App\Entity\Member;
use App\Form\ExpenseType;
use App\Ledger\Currency;
use App\Ledger\ExpenseDraft;
use App\Ledger\ExpenseRecorder;
use App\Ledger\LedgerBook;
use App\Security\HouseholdVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The lines of an account: noted, looked at, corrected, taken off. Anyone in the coloc can do any of it. */
#[Route('/comptes/{id}/depenses', requirements: ['id' => '\d+'])]
final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly ExpenseRecorder $recorder,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Paid today by the one who notes it, for everyone. From the balances, a reimbursement: ?type=transfert, who
     * hands it (de), who gets it (a), how much (montant, in cents) — to be changed before it is noted.
     */
    #[Route('/nouvelle', name: 'expense_new', methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    public function new(
        Ledger $ledger,
        #[CurrentUser] Member $member,
        Request $request,
        LedgerBook $book,
        #[MapQueryParameter] ?string $type = null,
        #[MapQueryParameter] ?int $de = null,
        #[MapQueryParameter] ?int $a = null,
        #[MapQueryParameter] ?int $montant = null,
    ): Response {
        $book->welcome($ledger);
        $me = $ledger->participantFor($member);
        $participants = $ledger->participantsById();
        $draft = 'transfert' === $type
            ? ExpenseDraft::transfer($ledger, $participants[$de] ?? $me, $participants[$a] ?? null, null !== $montant && $montant > 0 ? $montant : null, $this->clock->now())
            : ExpenseDraft::new($ledger, $me ?? ($participants ? reset($participants) : null), $this->clock->now());
        $form = $this->createForm(ExpenseType::class, $draft, ['ledger' => $ledger])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $expense = $this->recorder->record($draft, $member);
            $this->addFlash('success', $expense->isTransfer()
                ? \sprintf('Remboursement noté : %s.', LedgerController::reimbursement($expense->getPaidBy(), $expense->getRecipient() ?? $expense->getPaidBy(), Currency::format($expense->getConvertedAmount(), $ledger->getCurrency()), $member))
                : \sprintf('C’est noté : %s, %s.', $expense->getTitle(), Currency::format($expense->getAmount(), $expense->getCurrency())));

            return $this->redirectToRoute('ledger_show', ['id' => $ledger->getId()] + ($expense->isTransfer() ? ['vue' => LedgerController::BALANCES] : []), Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $ledger, $member, null, $this->generateUrl('ledger_show', ['id' => $ledger->getId()] + ('transfert' === $type ? ['vue' => LedgerController::BALANCES] : [])));
    }

    #[Route('/{expense}', name: 'expense_show', requirements: ['expense' => '\d+'], methods: ['GET'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    public function show(Ledger $ledger, #[MapEntity(id: 'expense')] Expense $expense, #[CurrentUser] Member $member): Response
    {
        $this->assertIn($ledger, $expense);

        return $this->render('expense/show.html.twig', [
            'ledger' => $ledger,
            'expense' => $expense,
            'me' => $ledger->participantFor($member),
        ]);
    }

    #[Route('/{expense}/modifier', name: 'expense_edit', requirements: ['expense' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    public function edit(Ledger $ledger, #[MapEntity(id: 'expense')] Expense $expense, #[CurrentUser] Member $member, Request $request, LedgerBook $book): Response
    {
        $this->assertIn($ledger, $expense);
        $book->welcome($ledger);
        $draft = ExpenseDraft::of($expense);
        $form = $this->createForm(ExpenseType::class, $draft, ['ledger' => $ledger])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->recorder->record($draft, $member, $expense);
            $this->addFlash('success', \sprintf('« %s » : c’est corrigé.', $expense->getTitle()));

            return $this->redirectToRoute('expense_show', ['id' => $ledger->getId(), 'expense' => $expense->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $ledger, $member, $expense, $this->generateUrl('expense_show', ['id' => $ledger->getId(), 'expense' => $expense->getId()]));
    }

    #[Route('/{expense}/supprimer', name: 'expense_delete', requirements: ['expense' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'ledger')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function delete(Ledger $ledger, #[MapEntity(id: 'expense')] Expense $expense): RedirectResponse
    {
        $this->assertIn($ledger, $expense);
        $title = $expense->getTitle();
        $transfer = $expense->isTransfer();
        $this->recorder->delete($expense);
        $this->addFlash('success', \sprintf('« %s » est retiré%s du compte.', $title, $transfer ? '' : 'e'));

        return $this->redirectToRoute('ledger_show', ['id' => $ledger->getId()] + ($transfer ? ['vue' => LedgerController::BALANCES] : []), Response::HTTP_SEE_OTHER);
    }

    /** @param FormInterface<ExpenseDraft> $form */
    private function renderForm(FormInterface $form, Ledger $ledger, Member $member, ?Expense $expense, string $back): Response
    {
        /** @var ExpenseDraft $draft */
        $draft = $form->getData();

        return $this->render('expense/form.html.twig', [
            'form' => $form,
            'ledger' => $ledger,
            'expense' => $expense,
            'draft' => $draft,
            'shares' => $draft->shares() ?? [],
            'participants' => $ledger->participantsById(),
            'me' => $ledger->participantFor($member),
            'symbols' => Currency::symbols(),
            'back' => $back,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function assertIn(Ledger $ledger, Expense $expense): void
    {
        if ($expense->getLedger() !== $ledger) {
            throw $this->createNotFoundException();
        }
    }
}
