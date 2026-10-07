<?php

namespace App\Twig;

use App\Task\Bonus;
use App\Task\BonusPolicy;
use App\Task\TaskView;
use Twig\Attribute\AsTwigFunction;

/** Shows upfront what a task would earn if done right now. */
final readonly class TaskBonusPreview
{
    public function __construct(
        private BonusPolicy $bonusPolicy,
    ) {
    }

    #[AsTwigFunction('task_bonus')]
    public function bonus(TaskView $view): ?Bonus
    {
        return $this->bonusPolicy->bonusFor($view->task, $view->status);
    }
}
