<?php

namespace App\Controller;

use App\Entity\CatalogItem;
use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskKind;
use App\Form\LogCompletionType;
use App\Form\TaskType;
use App\Repository\CatalogItemRepository;
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
 * The "+" button: first say what it is about, then fill in only what matters for it.
 * Something to do once (maybe from the catalogue), a new template that comes back on its own,
 * or noting what was already done.
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

    #[Route('', name: 'task_new', methods: ['GET'])]
    public function choose(#[MapQueryParameter] ?int $modele = null): Response
    {
        // Old links to a catalogue item: straight to the task to do.
        if (null !== $modele) {
            return $this->redirectToRoute('task_new_one_off', ['modele' => $modele]);
        }

        return $this->render('task/new_choose.html.twig');
    }

    /**
     * Something to do: first pick it among the tasks the house already knows (the catalogue, the
     * occasional tasks asleep), or else create a new one — which joins the catalogue for next time.
     */
    #[Route('/a-faire', name: 'task_new_one_off', methods: ['GET', 'POST'])]
    public function oneOff(
        #[CurrentUser] Member $member,
        Request $request,
        CatalogItemRepository $catalog,
        TaskBoardBuilder $boards,
        #[MapQueryParameter] ?int $modele = null,
        #[MapQueryParameter] bool $fait = false,
        #[MapQueryParameter] bool $nouvelle = false,
        #[MapQueryParameter] ?string $titre = null,
    ): Response {
        $household = $member->getHousehold();
        $items = $catalog->findForHousehold($household);
        $dormant = $boards->build($household)->dormant();
        if ($request->isMethod('GET') && null === $modele && !$fait && !$nouvelle && ([] !== $items || [] !== $dormant)) {
            return $this->render('task/new_pick.html.twig', ['catalog' => $items, 'dormant' => $dormant]);
        }

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
            'can_pick' => [] !== $items || [] !== $dormant,
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

        if (null !== $doneAt) {
            $this->celebrate($this->completer->complete($task, $member, $doneAt), $member);
        } elseif ($task->getKind()->isRecurring()) {
            $this->addFlash('success', \sprintf('Le modèle « %s » est créé : ses tâches arriveront ici quand ce sera le moment.', $task->getTitle()));
        } else {
            $this->addFlash('success', \sprintf('« %s » est dans la liste. %s', $task->getTitle(), $task->reservedByAt($this->clock->now()) ? 'Tu t’en occupes.' : 'Je préviens la coloc.'));
        }

        return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
    }
}
