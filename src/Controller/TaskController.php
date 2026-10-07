<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\CatalogItemRepository;
use App\Security\HouseholdVoter;
use App\Task\TaskBoardBuilder;
use App\Task\TaskCompleter;
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

        return $this->render('task/index.html.twig', [
            'recurring' => $board->recurring($zone),
            'one_off' => $board->oneOff(),
            'zones' => $household->getZones(),
            'current_zone' => $zone,
            'catalog' => $catalog->findForHousehold($household),
        ]);
    }

    #[Route('/nouvelle', name: 'task_new', methods: ['GET', 'POST'])]
    public function new(
        #[CurrentUser] Member $member,
        Request $request,
        CatalogItemRepository $catalog,
        #[MapQueryParameter] ?int $modele = null,
    ): Response {
        $household = $member->getHousehold();
        $task = new Task($household, $member, $this->clock->now());

        $template = null !== $modele ? $catalog->find($modele) : null;
        if (null !== $template && $template->getHousehold() === $household) {
            $task->setTitle($template->getTitle());
            $task->setCategory($template->getCategory());
            $task->setPoints($template->getPoints());
        }

        $form = $this->createForm(TaskType::class, $task, ['household' => $household, 'allow_reservation' => true])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->normalizeSchedule();
            if ($form->get('reserve')->getData()) {
                $task->reserveFor($member, $this->clock->now()->modify(\sprintf('+%d hours', Task::DEFAULT_RESERVATION_HOURS)));
            }
            $this->entityManager->persist($task);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('« %s » est dans la liste. %s', $task->getTitle(), $task->reservedByAt($this->clock->now()) ? 'Tu t’en occupes.' : 'Je préviens la coloc.'));

            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/new.html.twig', [
            'form' => $form,
            'catalog' => $catalog->findForHousehold($household),
            'template' => $template,
        ]);
    }

    #[Route('/{id}/modifier', name: 'task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    public function edit(Task $task, Request $request): Response
    {
        $form = $this->createForm(TaskType::class, $task, ['household' => $task->getHousehold()])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->normalizeSchedule();
            $this->entityManager->flush();
            $this->addFlash('success', 'C’est enregistré.');

            return $this->redirectToRoute('task_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/edit.html.twig', ['form' => $form, 'task' => $task]);
    }

    #[Route('/{id}/fait', name: 'task_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HouseholdVoter::ACCESS, subject: 'task')]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function complete(Task $task, #[CurrentUser] Member $member, Request $request, TaskCompleter $completer): RedirectResponse
    {
        if ($task->isArchived()) {
            throw $this->createNotFoundException();
        }

        $result = $completer->complete($task, $member);
        $this->addFlash('completion', ['points' => $result->totalPoints(), 'title' => $task->getTitle()]);

        return $this->redirectBack($request);
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

    private function redirectBack(Request $request): RedirectResponse
    {
        $route = self::BACK_ROUTES[$request->request->getString('_back')] ?? 'app_home';

        return $this->redirectToRoute($route, status: Response::HTTP_SEE_OTHER);
    }
}
