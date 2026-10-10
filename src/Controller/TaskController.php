<?php

namespace App\Controller;

use App\Entity\CatalogItem;
use App\Entity\Member;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\TaskCategory;
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

    /** How the list of templates can be sorted: by room, by kind of work, by how they come back. */
    private const GROUPINGS = ['piece' => 'Pièce', 'categorie' => 'Catégorie', 'frequence' => 'Fréquence'];

    #[Route('', name: 'task_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] Member $member,
        TaskBoardBuilder $boards,
        CatalogItemRepository $catalog,
        #[MapQueryParameter] string $par = 'piece',
    ): Response {
        $household = $member->getHousehold();
        $par = \array_key_exists($par, self::GROUPINGS) ? $par : 'piece';
        // The templates only: the tasks to do, one-off ones included, are on the home page.
        $templates = array_values(array_filter(
            array_map(static fn (TaskView $view): Task => $view->task, $boards->build($household)->items),
            static fn (Task $task): bool => $task->getKind()->isRecurring(),
        ));

        return $this->render('task/index.html.twig', [
            'groups' => $this->group($templates, $par),
            'grouping' => $par,
            'groupings' => self::GROUPINGS,
            'count' => \count($templates),
            'catalog' => $catalog->findForHousehold($household),
        ]);
    }

    /**
     * @param list<Task> $templates
     *
     * @return list<array{key: string, label: string, hint: ?string, tasks: non-empty-list<Task>}> in a natural order, each by title
     */
    private function group(array $templates, string $by): array
    {
        $groups = [];
        foreach ($templates as $task) {
            // By room, a task in several rooms is in each of them.
            $places = 'piece' === $by ? $task->getZones()->map(static fn (Zone $zone): array => ['zone-'.$zone->getId(), $zone->getName(), null, 0])->toArray() : [];
            foreach ([] !== $places ? $places : [match ($by) {
                'categorie' => [$task->getCategory()->value, $task->getCategory()->label(), null, array_search($task->getCategory(), TaskCategory::cases(), true)],
                'frequence' => [$task->getKind()->value, $task->getKind()->label(), match ($task->getKind()) {
                    TaskKind::Rolling => 'Tous les tant de jours, une fois faite.',
                    TaskKind::Scheduled => 'Un jour et une heure fixes.',
                    TaskKind::Quick => 'Un appui sur l’accueil, « En un geste ».',
                    TaskKind::Occasional => 'Elles dorment jusqu’à ce que ça arrive.',
                    TaskKind::OneOff => null,
                }, array_search($task->getKind(), [TaskKind::Rolling, TaskKind::Scheduled, TaskKind::Quick, TaskKind::Occasional], true)],
                default => ['coloc', 'Toute la coloc', null, 1],
            }] as [$key, $label, $hint, $rank]) {
                $groups[$key] ??= ['key' => $by.'-'.$key, 'label' => $label, 'hint' => $hint, 'rank' => $rank, 'tasks' => []];
                $groups[$key]['tasks'][] = $task;
            }
        }

        $collator = new \Collator('fr_FR');
        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank'] ?: (int) $collator->compare($a['label'], $b['label']));

        return array_map(static function (array $group) use ($collator): array {
            usort($group['tasks'], static fn (Task $a, Task $b): int => (int) $collator->compare($a->getTitle(), $b->getTitle()));
            unset($group['rank']);

            return $group;
        }, $groups);
    }

    /** "En un geste" on the home page: which express tasks, in which order. */
    #[Route('/en-un-geste', name: 'task_quick_settings', methods: ['GET'])]
    public function quickSettings(#[CurrentUser] Member $member, TaskBoardBuilder $boards): Response
    {
        return $this->render('task/quick_settings.html.twig', [
            'tasks' => array_map(static fn (TaskView $view): Task => $view->task, $boards->build($member->getHousehold())->quick()),
        ]);
    }

    /** One place up or down in "En un geste", or shown / left out. */
    #[Route('/{id}/en-un-geste', name: 'task_quick_arrange', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function quickArrange(Task $task, Request $request, TaskBoardBuilder $boards): RedirectResponse
    {
        if ($task->isArchived() || TaskKind::Quick !== $task->getKind()) {
            throw $this->createNotFoundException();
        }
        $tasks = array_map(static fn (TaskView $view): Task => $view->task, $boards->build($task->getHousehold())->quick());
        $index = (int) array_search($task, $tasks, true);
        $action = $request->request->getString('action');
        $other = match ($action) {
            'monter' => $index - 1,
            'descendre' => $index + 1,
            default => null,
        };
        if (null !== $other && isset($tasks[$other])) {
            [$tasks[$index], $tasks[$other]] = [$tasks[$other], $tasks[$index]];
        }
        if ('afficher' === $action || 'masquer' === $action) {
            $task->setQuickHidden('masquer' === $action);
        }
        // Every one gets its place: the order is the one shown on this page.
        foreach ($tasks as $position => $quick) {
            $quick->setQuickPosition($position + 1);
        }
        $this->entityManager->flush();

        return $this->redirectToRoute('task_quick_settings', status: Response::HTTP_SEE_OTHER);
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

    /** Changed one's mind: the task taken goes back to the pile, "Je prends" again for everyone. */
    #[Route('/{id}/je-laisse', name: 'task_release', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function release(Task $task, #[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        if ($task->reservedByAt($this->clock->now()) === $member) {
            $task->release();
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

    /** A task of the catalogue no longer needed. */
    #[Route('/catalogue/{id}/retirer', name: 'catalog_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'item')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function deleteCatalogItem(CatalogItem $item): Response
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('« %s » est retirée du catalogue.', $item->getTitle()));

        return $this->redirectToRoute('task_index', status: Response::HTTP_SEE_OTHER);
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
