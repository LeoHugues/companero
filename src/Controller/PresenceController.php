<?php

namespace App\Controller;

use App\Entity\Member;
use App\Presence\PresenceRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/** "I'm home" / "I'm out": from the app, or from the phone's quick settings tile. */
final class PresenceController extends AbstractController
{
    /**
     * Sent by the Android app (quick settings tile). Browsers cannot add it to a cross-site
     * request without a CORS preflight, which is never granted: it protects the API from CSRF.
     */
    public const APP_HEADER = 'X-Companero-App';

    private const BACK_ROUTES = ['home' => 'app_home', 'profile' => 'profile_show'];

    public function __construct(
        private readonly PresenceRecorder $recorder,
    ) {
    }

    #[Route('/presence/basculer', name: 'presence_toggle', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_csrf_token')]
    public function toggle(#[CurrentUser] Member $member, Request $request): RedirectResponse
    {
        $this->recorder->setAtHome($member, !$member->isAtHome());
        $this->addFlash('success', $member->isAtHome() ? 'Bon retour à la maison !' : 'À plus tard ! Les rappels iront aux colocs présents.');

        return $this->redirectToRoute(self::BACK_ROUTES[$request->request->getString('_back')] ?? 'app_home', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/api/presence', name: 'api_presence_show', methods: ['GET'])]
    public function show(#[CurrentUser] Member $member): JsonResponse
    {
        return $this->state($member);
    }

    /** Body: {"atHome": true|false}, or nothing to switch. */
    #[Route('/api/presence', name: 'api_presence_update', methods: ['POST'])]
    public function update(#[CurrentUser] Member $member, Request $request): JsonResponse
    {
        if (!$request->headers->has(self::APP_HEADER)) {
            throw new BadRequestHttpException(\sprintf('The %s header is required.', self::APP_HEADER));
        }

        $body = '' === $request->getContent() ? [] : $request->toArray();
        $this->recorder->setAtHome($member, isset($body['atHome']) ? (bool) $body['atHome'] : !$member->isAtHome());

        return $this->state($member);
    }

    private function state(Member $member): JsonResponse
    {
        return new JsonResponse([
            'name' => $member->getName(),
            'atHome' => $member->isAtHome(),
            'since' => $member->getAtHomeChangedAt()?->format(\DATE_ATOM),
        ]);
    }
}
