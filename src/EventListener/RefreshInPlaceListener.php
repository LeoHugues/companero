<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\UX\Turbo\TurboBundle;

/**
 * A form that sends you back to the page it was on ("C'est fait" on the home page, a gift
 * opened in the profile…) refreshes that page in place: instead of the redirect, Turbo gets the
 * page's new <main> and morphs it in. The celebration, the gauges that rise, the Casa that
 * jumps all play out on the spot, and the Android app has no new screen to push — it used to
 * rebuild the home page from scratch, losing what was just earned.
 *
 * Only for Turbo form submissions (they accept Turbo Streams); a redirect elsewhere is kept.
 */
#[AsEventListener(priority: -64)]
final readonly class RefreshInPlaceListener
{
    public function __construct(
        private HttpKernelInterface $kernel,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest()
            || !$response instanceof RedirectResponse
            || !$request->isMethod('POST')
            || TurboBundle::STREAM_FORMAT !== $request->getPreferredFormat()
            || !$this->isSamePage($request->headers->get('referer'), $response->getTargetUrl())
        ) {
            return;
        }

        $page = $this->render($request, $response->getTargetUrl());
        if (null === $page) {
            return;
        }

        $event->setResponse(new Response(
            \sprintf('<turbo-stream action="update" method="morph" targets="body > main"><template>%s</template></turbo-stream>', $page),
            Response::HTTP_OK,
            ['Content-Type' => 'text/vnd.turbo-stream.html; charset=UTF-8'],
        ));
    }

    /** The redirect goes back to the page the form was sent from (its anchor aside). */
    private function isSamePage(?string $referer, string $target): bool
    {
        if (null === $referer) {
            return false;
        }
        $from = parse_url($referer);
        $to = parse_url($target);

        return ($from['path'] ?? '/') === ($to['path'] ?? '/') && ($from['query'] ?? '') === ($to['query'] ?? '');
    }

    /** The inside of the page's <main>, rendered as a GET would (flashes included), or null if it is not a plain page. */
    private function render(Request $request, string $target): ?string
    {
        $page = Request::create($target, 'GET', [], $request->cookies->all(), [], $request->server->all());
        $page->headers->set('Accept', 'text/html');
        if ($request->hasSession()) {
            $page->setSession($request->getSession());
        }

        $response = $this->kernel->handle($page, HttpKernelInterface::SUB_REQUEST);
        if (!$response->isSuccessful() || 1 !== preg_match('#<main\b[^>]*>(.*)</main>#s', (string) $response->getContent(), $main)) {
            return null;
        }

        return $main[1];
    }
}
