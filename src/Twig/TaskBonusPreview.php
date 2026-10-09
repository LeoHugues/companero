<?php

namespace App\Twig;

use App\Entity\Member;
use App\Reward\ActiveBoost;
use App\Reward\BoostResolver;
use App\Task\TaskView;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/** Shows upfront what a task would earn if done right now, by the member looking at it. */
final readonly class TaskBonusPreview
{
    public function __construct(
        private BoostResolver $boosts,
        private Security $security,
        private ClockInterface $clock,
    ) {
    }

    /** Extra points from the boost in effect for the current member. */
    #[AsTwigFunction('task_boost')]
    public function boost(TaskView $view): int
    {
        return $this->activeBoosts()['points']?->bonusFor($view->task->getCurrentPoints()) ?? 0;
    }

    /** @return array{points: ?ActiveBoost, xp: ?ActiveBoost} */
    #[AsTwigFunction('active_boosts')]
    public function activeBoosts(): array
    {
        $member = $this->security->getUser();
        if (!$member instanceof Member) {
            return ['points' => null, 'xp' => null];
        }
        $now = $this->clock->now();

        return ['points' => $this->boosts->points($member, $now), 'xp' => $this->boosts->xp($member, $now)];
    }
}
