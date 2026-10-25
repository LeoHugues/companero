<?php

namespace App\Controller;

use App\Entity\CatalogItem;
use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskKind;
use App\Form\LogCompletionType;
use App\Form\TaskType;
use App\Repository\CatalogItemRepository;
use App\Shopping\ShoppingListBuilder;
use App\Task\TaskBoardBuilder;
use App\Task\TaskCompleter;
use App\Task\TaskNotAvailable;
use App\Twig\TaskLabels;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The "+" button: a sheet says what can be added — something to do once (maybe from the catalogue, or an occasional
 * task that happens), what was already done, an expense, a template that comes back on its own — and takes what is
 * missing right there. Each form asks only what matters for it, the rest folded away.
 */
#[Route('/taches/nouvelle')]
final class TaskCreationController extends AbstractController
{
    use CelebratesCompletions;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskCompleter $completer,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * The sheet "Ajouter": loaded into the sheet of the bottom bar (a Turbo Frame), or a page of its own (in the app,
     * without scripts). What is missing goes back to the page it was said on.
     */
    #[Route('', name: 'task_new', methods: ['GET'])]
    public function choose(#[CurrentUser] Member $member, Request $request, ShoppingListBuilder $lists, #[MapQueryParameter] ?int $modele = null): Response
    {
        // Old links to a catalogue item: straight to the task to do.
        if (null !== $modele) {
            return $this->redirectToRoute('task_new_one_off', ['modele' => $modele]);
        }
        $inSheet = 'add-sheet' === $request->headers->get('Turbo-Frame');
        $from = parse_url((string) $request->headers->get('referer'));
        $back = $inSheet && ($from['host'] ?? null) === $request->getHost() ? ($from['path'] ?? '/').(isset($from['query']) ? '?'.$from['query'] : '') : $this->generateUrl('task_new');

        return $this->render($inSheet ? 'task/add_sheet.html.twig' : 'task/new_choose.html.twig', [
            'suggestions' => \array_slice($lists->build($member->getHousehold())->suggestions, 0, 5),
            'back' => $back,
        ]);
    }

    /**
     * Something to do, in a few words: what, when, who. As it is typed, the tasks the house already knows show up
     * (the catalogue, launched as it is; the occasional ones asleep, raised in one tap). The rest is folded away.
     */
    #[Route('/a-faire', name: 'task_new_one_off', methods: ['GET', 'POST'])]
    public function oneOff(
        #[CurrentUser] Member $member,
        Request $request,
        CatalogItemRepository $catalog,
        TaskBoardBuilder $boards,
        #[MapQueryParameter] ?int $modele = null,
        #[MapQueryParameter] bool $fait = false,
        #[MapQueryParameter] ?string $titre = null,
    ): Response {
        $household = $member->getHousehold();

        $task = new Task($household, $member, $this->clock->now());
        $task->setKind(TaskKind::OneOff);

        $template = null !== $modele ? $catalog->find($modele) : null;
        if (null !== $template && $template->getHousehold() === $household) {
            $template->fill($task);
        } else {
            $template = null;
            $task->setTitle(mb_substr(trim($titre ?? ''), 0, 120));
        }

        $form = $this->createForm(TaskType::class, $task, [
            'household' => $household,
            'mode' => TaskType::ONE_OFF,
            'allow_reservation' => true,
            'allow_done' => true,
            'allow_catalog' => null === $template,
        ]);
        if ($fait) {
            $form->get('done')->setData(true);
        }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('catalog') && $form->get('catalog')->getData() && null === $catalog->findOneByTitle($household, $task->getTitle())) {
                $this->entityManager->persist(new CatalogItem($household, $task->getTitle(), $task->getCategory(), $task->getPoints(), $task->getZones()->first() ?: null));
            }

            return $this->save($task, $form, $member);
        }

        return $this->render('task/new_one_off.html.twig', [
            'form' => $form,
            'template' => $template,
            'catalog' => null === $template ? $catalog->findForHousehold($household) : [],
            'dormant' => null === $template ? $boards->build($household)->dormant() : [],
        ]);
    }

    #[Route('/modele', name: 'task_new_template', methods: ['GET', 'POST'])]
    public function template(#[CurrentUser] Member $member, Request $request): Response
    {
        $household = $member->getHousehold();
        $task = new Task($household, $member, $this->clock->now());
        $task->setKind(TaskKind::Rolling);

        $form = $this->createForm(TaskType::class, $task, ['household' => $household, 'mode' => TaskType::TEMPLATE, 'allow_done' => true])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->save($task, $form, $member);
        }

        return $this->render('task/new_template.html.twig', ['form' => $form]);
    }

    #[Route('/deja-fait', name: 'task_log', methods: ['GET', 'POST'])]
    public function log(#[CurrentUser] Member $member, Request $request, TaskLabels $labels): Response
    {
        $form = $this->createForm(LogCompletionType::class, null, ['household' => $member->getHousehold()])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Task $task */
            $task = $form->get('task')->getData();
            /** @var \DateTimeImmutable $doneAt */
            $doneAt = $form->get('doneAt')->getData();
            try {
                $this->celebrate($this->completer->complete($task, $member, $doneAt), $member);
            } catch (TaskNotAvailable $notYet) {
                $this->addFlash('success', \sprintf('« %s » vient d’être fait : à nouveau possible %s.', $task->getTitle(), $labels->availableAgain($notYet->availableAt)));
            }

            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/new_log.html.twig', ['form' => $form]);
    }

    /** @param FormInterface<Task> $form */
    private function save(Task $task, FormInterface $form, Member $member): Response
    {
        $task->normalizeSchedule();
        /** @var ?\DateTimeImmutable $doneAt */
        $doneAt = $form->get('done')->getData() ? ($form->get('doneAt')->getData() ?? $this->clock->now()) : null;
        if (null !== $doneAt) {
            $task->backdateCreation($doneAt);
        } elseif ($form->has('reserve') && $form->get('reserve')->getData()) {
            $task->reserveFor($member, $this->clock->now()->modify(\sprintf('+%d hours', Task::DEFAULT_RESERVATION_HOURS)));
        }
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        // Back where it shows: done, the Casa thanks; taken, it is in my hand; free, in the list of the coloc.
        if (null !== $doneAt) {
            $this->celebrate($this->completer->complete($task, $member, $doneAt), $member);
            $route = 'app_home';
        } elseif ($task->getKind()->isRecurring()) {
            // An express one is on the home page, "En un geste"; the others come to the list when it is time.
            $this->addFlash('success', \sprintf('Le modèle « %s » est créé : ses tâches arriveront quand ce sera le moment.', $task->getTitle()));
            $route = TaskKind::Quick === $task->getKind() ? 'app_home' : 'task_board';
        } else {
            $mine = null !== $task->reservedByAt($this->clock->now());
            $this->addFlash('success', \sprintf('« %s » est dans la liste. %s', $task->getTitle(), $mine ? 'Tu t’en occupes.' : 'Je préviens la coloc.'));
            $route = $mine ? 'app_home' : 'task_board';
        }

        return $this->redirectToRoute($route, status: Response::HTTP_SEE_OTHER);
    }
}
