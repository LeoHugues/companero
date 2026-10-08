<?php

namespace App\Controller;

use App\Entity\Member;
use App\Enum\TaskCategory;
use App\Task\TaskBoardBuilder;
use App\Task\TaskView;
use App\Twig\TaskLabels;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * What the member should be reminded of right now: the pressing tasks someone counts on them for,
 * and the pets' pressing tasks when they are home. Meant to be polled by the app to show notifications.
 */
final class ReminderController extends AbstractController
{
    #[Route('/api/rappels', name: 'api_reminders', methods: ['GET'])]
    public function __invoke(#[CurrentUser] Member $member, TaskBoardBuilder $boards, TaskLabels $labels): JsonResponse
    {
        $views = array_filter(
            $boards->build($member->getHousehold())->pressing(),
            static fn (TaskView $view): bool => true === $view->reminder?->concerns($member)
                && (null !== $view->task->getAssignee() || TaskCategory::Pets === $view->task->getCategory()),
        );

        return new JsonResponse(array_values(array_map(static fn (TaskView $view): array => [
            'id' => $view->task->getId(),
            'title' => $view->task->getTitle(),
            'status' => $labels->statusLabel($view->task, $view->status),
            'standingInFor' => $view->reminder?->standingInFor?->getName(),
            'urgency' => $view->status->urgency->value,
        ], $views)));
    }
}
