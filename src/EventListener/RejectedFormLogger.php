<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * A form sent and turned away (a CSRF token refused, an access denied) only shows as a redirect to the
 * login page, and the reason is logged as a mere warning, which production drops. This says why, at
 * error level: what the browser told about where the form came from, which cookies came with it (their
 * names only) and who was signed in.
 */
#[AsEventListener(priority: 16)]
final readonly class RejectedFormLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private TokenStorageInterface $tokens,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();
        if (!$event->isMainRequest() || $request->isMethodSafe()
            || !($exception instanceof AuthenticationException || $exception instanceof AccessDeniedException)
        ) {
            return;
        }

        $session = $request->hasPreviousSession() ? $request->getSession() : null;
        $this->logger->error('Form rejected: {reason} on {method} {path} (sec-fetch-site: {site}, origin: {origin}, referer: {referer}, cookies: {cookies}, session: {session}, csrf strategy: {strategy}, token: {token})', [
            'reason' => $exception->getMessage(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'site' => $request->headers->get('Sec-Fetch-Site', '-'),
            'origin' => $request->headers->get('Origin', '-'),
            'referer' => $request->headers->get('Referer', '-'),
            'cookies' => implode(' ', array_keys($request->cookies->all())) ?: '-',
            'session' => null === $session ? 'none' : 'previous',
            'strategy' => $session?->get('csrf-token') ?? '-',
            'token' => null === ($token = $this->tokens->getToken()) ? 'anonymous' : (new \ReflectionClass($token))->getShortName(),
        ]);
    }
}
