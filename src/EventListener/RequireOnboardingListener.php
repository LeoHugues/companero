<?php

namespace App\EventListener;

use App\Entity\Member;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The tour comes first: until someone has finished it, every page leads to it — except the tour
 * itself, logging out, and what the Android app fetches on its own (/api, /native).
 * It runs after the firewall (priority 8), which knows who is there.
 */
#[AsEventListener(priority: 4)]
final readonly class RequireOnboardingListener
{
    private const OPEN_ROUTES = ['app_logout', 'app_login'];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if ('' === $route || str_starts_with($route, 'tour_') || str_starts_with($route, 'onboarding_') || str_starts_with($route, '_') || \in_array($route, self::OPEN_ROUTES, true) || preg_match('#^/(api|native)/#', $request->getPathInfo())) {
            return;
        }

        $member = $this->security->getUser();
        if ($member instanceof Member && !$member->isOnboarded()) {
            $event->setResponse(new RedirectResponse($this->urls->generate('tour_start')));
        }
    }
}
