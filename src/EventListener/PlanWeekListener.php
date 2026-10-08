<?php

namespace App\EventListener;

use App\Bounty\WeekPlanner;
use App\Calendar\Week;
use App\Entity\Member;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * The week is planned (its surprises drawn) by app:week:close on Monday night; should the
 * command not have run, the first page shown that week does it.
 */
#[AsEventListener]
final readonly class PlanWeekListener
{
    public function __construct(
        private Security $security,
        private WeekPlanner $planner,
        private ClockInterface $clock,
        #[Autowire('%app.plan_weeks_on_visit%')] private bool $enabled,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest() || !$event->getRequest()->isMethod('GET')) {
            return;
        }
        $member = $this->security->getUser();
        if ($member instanceof Member) {
            $this->planner->plan($member->getHousehold(), Week::containing($this->clock->now()));
        }
    }
}
