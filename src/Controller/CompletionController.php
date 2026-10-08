<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\Member;
use App\Form\CompletionType;
use App\Repository\PointEntryRepository;
use App\Security\HouseholdVoter;
use App\Task\CompletionChange;
use App\Task\TaskCompleter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Puts a completion right: noted days later, by the wrong person, or worth more (or less) than usual. */
final class CompletionController extends AbstractController
{
    #[Route('/realisations/{id}/modifier', name: 'completion_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'completion')]
    public function edit(
        Completion $completion,
        #[CurrentUser] Member $member,
        Request $request,
        TaskCompleter $completer,
        PointEntryRepository $points,
        ClockInterface $clock,
        #[MapQueryParameter] ?string $retour = null,
    ): Response {
        $change = CompletionChange::of($completion, $points->basePointsOf($completion));
        $form = $this->createForm(CompletionType::class, $change, ['household' => $completion->getTask()->getHousehold()])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $completer->amend($completion, $change->member, $change->completedAt, $change->points);
            $this->addFlash('success', \sprintf('« %s » : c’est corrigé.', $completion->getTask()->getTitle()));

            if ('bilan' === $retour) {
                return $this->redirectToRoute('review_show', ['week' => Week::containing($completion->getCompletedAt())->start->format('Y-m-d')], Response::HTTP_SEE_OTHER);
            }
            $daysAgo = (int) $completion->getCompletedAt()->setTime(0, 0)->diff($clock->now()->setTime(0, 0))->days;

            return $this->redirectToRoute('history', [
                'qui' => $completion->getMember() === $member ? 'moi' : 'coloc',
                'jours' => $daysAgo < 7 ? 7 : 30,
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('completion/edit.html.twig', [
            'form' => $form,
            'completion' => $completion,
            'total' => $points->sumByCompletion([$completion])[$completion->getId()] ?? 0,
            'retour' => $retour,
        ]);
    }
}
