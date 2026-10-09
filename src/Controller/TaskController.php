<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskKind;
use App\Form\TaskType;
use App\Repository\CatalogItemRepository;
use App\Repository\CompletionRepository;
use App\Repository\PointEntryRepository;
use App\Security\HouseholdVoter;
use App\Task\TaskBoardBuilder;
use App\Task\TaskCompleter;
use App\Task\TaskNotAvailable;
use App\Task\TaskView;
use App\Twig\TaskLabels;
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

#[Route('/taches')]
final class TaskController extends AbstractController
{
    use CelebratesCompletions;

    /** Where the action buttons may send the member back to. */
    private const BACK_ROUTES = ['home' => 'app_home', 'tasks' => 'task_index'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'task_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] Member $member,
        TaskBoardBuilder $boards,
        CatalogItemRepository $catalog,
        #[MapQueryParameter] ?int $zone = null,
    ): Response {
        $household = $member->getHousehold();
        $board = $boards->build($household);

        // The templates only: the tasks to do, one-off ones included, are on the home page.
        return $this->render('task/index.html.twig', [
            'recurring' => array_map(static fn (TaskView $view): Task => $view->task, $board->recurring($zone)),
            'quick' => array_map(static fn (TaskView $view): Task => $view->task, $board->quick()),
            'occasional' => array_map(static fn (TaskView $view): Task => $view->task, $board->occasional($zone)),
            'zones' => $household->getZones(),
            'current_zone' => $zone,
            'catalog' => $catalog->findForHousehold($household),
        ]);
    }

    #[Route('/{id}', name: 'task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    public function show(Task $task, TaskBoardBuilder $boards, CompletionRepository $completions, PointEntryRepository $points): Response
    {
        if ($task->isArchived()) {
            throw $this->createNotFoundException();
        }
        $recent = $completions->findRecentForTask($task);

        return $this->render('task/show.html.twig', [
            'view' => $boards->view($task),
            'completions' => $recent,
            'points' => $points->sumByCompletion($recent),
            'now' => $this->clock->now(),
        ]);
    }

    #[Route('/{id}/modele', name: 'task_template', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    public function template(Task $task, TaskBoardBuilder $boards, CompletionRepository $completions, PointEntryRepository $points): Response
    {
        // A one-off task is not a template: it is something to do.
        if ($task->isArchived() || !$task->getKind()->isRecurring()) {
            throw $this->createNotFoundException();
        }
        $now = $this->clock->now();
        $recent = $completions->findRecentForTask($task, 5);

        return $this->render('task/template.html.twig', [
            'view' => $boards->view($task),
            'completions' => $recent,
            'points' => $points->sumByCompletion($recent),
            'done_last_30_days' => $completions->countForTask($task, $now->modify('-30 days'), $now->modify('+1 second')),
        ]);
    }

    #[Route('/{id}/modifier', name: 'task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    public function edit(Task $task, Request $request): Response
    {
        $form = $this->createForm(TaskType::class, $task, [
            'household' => $task->getHousehold(),
            'mode' => $task->getKind()->isRecurring() ? TaskType::TEMPLATE : TaskType::ONE_OFF,
        ])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->normalizeSchedule();
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute($task->getKind()->isRecurring() ? 'task_template' : 'task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/edit.html.twig', ['form' => $form, 'task' => $task]);
    }

    #[Route('/{id}/fait', name: 'task_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function complete(Task $task, #[CurrentUser] Member $member, Request $request, TaskCompleter $completer, TaskLabels $labels): RedirectResponse
    {
        if ($task->isArchived()) {
            throw $this->createNotFoundException();
        }

        try {
            $result = $completer->complete($task, $member);
        } catch (TaskNotAvailable $notYet) {
            $this->addFlash('success', \sprintf('« %s » vient d’être fait%s : à nouveau possible %s.', $task->getTitle(), null !== $task->getLastCompletedBy() ? ' par '.$task->getLastCompletedBy()->getName() : '', $labels->availableAgain($notYet->availableAt)));

            return $this->redirectBack($request);
        }
        $this->celebrate($result, $member);

        return $this->redirectBack($request, $task);
    }

    #[Route('/{id}/je-m-en-occupe', name: 'task_reserve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function reserve(Task $task, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $now = $this->clock->now();
        if (null === $task->reservedByAt($now)) {
            $task->reserveFor($member, $now->modify(\sprintf('+%d hours', Task::DEFAULT_RESERVATION_HOURS)));
            $this->entityManager->flush();
        }

        return $this->redirectBack($request);
    }

    /** "Ça arrive": an occasional task is needed now — its card shows up on the home page until someone does it. */
    #[Route('/{id}/ca-arrive', name: 'task_raise', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function raise(Task $task, Request $request): RedirectResponse
    {
        if ($task->isArchived() || TaskKind::Occasional !== $task->getKind()) {
            throw $this->createNotFoundException();
        }
        if (!$task->isRaised()) {
            $task->raise($this->clock->now());
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('« %s » : c’est signalé, sa carte attend sur l’accueil.', $task->getTitle()));
        }

        return $this->redirectBack($request);
    }

    /** "This time it is more (or less) work": the points of the task to do, not of its template. */
    #[Route('/{id}/points', name: 'task_adjust_points', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function adjustPoints(Task $task, Request $request): RedirectResponse
    {
        $delta = $request->request->getInt('ecart');
        if ($task->isArchived() || !\in_array(abs($delta), Task::POINT_STEPS, true)) {
            throw $this->createNotFoundException();
        }
        $task->adjustPoints($delta);
        $this->entityManager->flush();

        return $this->redirectToRoute('task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
    }

    /** A word about this time only: it goes away once the task is done. */
    #[Route('/{id}/note', name: 'task_note', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function note(Task $task, Request $request): RedirectResponse
    {
        if ($task->isArchived()) {
            throw $this->createNotFoundException();
        }
        $task->setNote(mb_substr($request->request->getString('note'), 0, Task::NOTE_MAX_LENGTH));
        $this->entityManager->flush();
        $this->addFlash('success', null !== $task->getNote() ? 'La note est ajoutée à la tâche.' : 'La note est retirée.');

        return $this->redirectToRoute('task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/supprimer', name: 'task_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function delete(Task $task): RedirectResponse
    {
        // Archived rather than deleted: its completions and points stay in the journal.
        $task->archive($this->clock->now());
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('« %s » a été retirée.', $task->getTitle()));

        return $this->redirectToRoute('task_index', status: Response::HTTP_SEE_OTHER);
    }

    private function redirectBack(Request $request, ?Task $task = null): RedirectResponse
    {
        $back = $request->request->getString('_back');
        if (1 === preg_match('/^zone-(\d+)$/', $back, $zone)) {
            return $this->redirectToRoute('plan_zone', ['id' => (int) $zone[1]], Response::HTTP_SEE_OTHER);
        }
        if (1 === preg_match('/^template-(\d+)$/', $back, $id)) {
            return $this->redirectToRoute('task_template', ['id' => (int) $id[1]], Response::HTTP_SEE_OTHER);
        }
        if (1 === preg_match('/^task-(\d+)$/', $back, $id)) {
            // A one-off task is gone once done: back home then.
            return null !== $task && $task->isArchived()
                ? $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER)
                : $this->redirectToRoute('task_show', ['id' => (int) $id[1]], Response::HTTP_SEE_OTHER);
        }
        $route = self::BACK_ROUTES[$back] ?? 'app_home';

        return $this->redirectToRoute($route, status: Response::HTTP_SEE_OTHER);
    }
}
