<?php

namespace App\Controller;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\PetSpecies;
use App\Form\FoundingType;
use App\Form\RegistrationType;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use App\Household\StarterKit;
use App\Repository\HouseholdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

/**
 * Arriving. Creating the household, one question at a time, then setting it up — its rooms, its animals, its cleaning
 * day, the chores it starts with — and inviting the others; or joining it with the invitation link, or claiming the
 * profile waiting there. Then the tour (TourController).
 */
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/bienvenue', name: 'onboarding_found', methods: ['GET', 'POST'])]
    public function found(Request $request, HouseholdFounder $founder): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $founding = new Founding();
        $form = $this->createForm(FoundingType::class, $founding)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->logIn($founder->found($founding), 'onboarding_house');
        }

        return $this->render('onboarding/found.html.twig', ['form' => $form]);
    }

    /** The household's rooms, its animals and its cleaning day: right after it is created, before any task. */
    #[Route('/bienvenue/maison', name: 'onboarding_house', methods: ['GET', 'POST'])]
    public function house(#[CurrentUser] Member $member, Request $request): Response
    {
        if (null !== $next = $this->setUpAlready($member)) {
            return $next;
        }
        $household = $member->getHousehold();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException();
            }
            $this->keepRooms($household, $request);
            $this->keepPets($household, $request);
            $day = $request->request->getInt('jour');
            if ($day >= 1 && $day <= 7) {
                $household->setCleaningDay($day);
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('onboarding_starter', status: Response::HTTP_SEE_OTHER);
        }

        $rooms = array_keys(StarterKit::ROOMS);
        foreach ($household->getZones() as $zone) {
            if (!\in_array($zone->getName(), $rooms, true)) {
                $rooms[] = $zone->getName();
            }
        }

        return $this->render('onboarding/house.html.twig', [
            'household' => $household,
            'rooms' => $rooms,
            'chosen' => array_map(static fn (Zone $zone): string => $zone->getName(), $household->getZones()->toArray()),
        ]);
    }

    /** The chores the household starts with, the usual ones ticked. */
    #[Route('/bienvenue/pour-commencer', name: 'onboarding_starter', methods: ['GET', 'POST'])]
    public function starter(#[CurrentUser] Member $member, Request $request, StarterKit $kit): Response
    {
        if (null !== $next = $this->setUpAlready($member)) {
            return $next;
        }
        $household = $member->getHousehold();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException();
            }
            $keys = array_values(array_filter($request->request->all('taches'), 'is_string'));
            $tasks = $kit->apply($household, $member, $this->clock->now(), $keys);
            foreach ($tasks as $task) {
                $this->entityManager->persist($task);
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('onboarding_invite', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('onboarding/starter.html.twig', ['household' => $household, 'offer' => $kit->offer($household)]);
    }

    /** The invitation link, to send to the others; their profiles can be prepared by their first names. */
    #[Route('/bienvenue/inviter', name: 'onboarding_invite', methods: ['GET', 'POST'])]
    public function invite(#[CurrentUser] Member $member, Request $request, MemberRegistrar $registrar): Response
    {
        if ($member->isOnboarded()) {
            return $this->redirectToRoute('household_edit');
        }
        $household = $member->getHousehold();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException();
            }
            $name = trim($request->request->getString('prenom'));
            if ('' === $name || mb_strlen($name) > 40) {
                $this->addFlash('success', 'Il faut un prénom, de 40 caractères au plus.');
            } elseif (null !== $namesake = $household->memberNamed($name)) {
                $this->addFlash('success', \sprintf('%s est déjà dans la coloc.', $namesake->getName()));
            } else {
                $registrar->addUnclaimed($household, $name);
            }

            return $this->redirectToRoute('onboarding_invite', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('onboarding/invite.html.twig', ['household' => $household]);
    }

    /** The invitation link pasted on the welcome page (in the app, a link opens the browser): to its household. */
    #[Route('/rejoindre', name: 'onboarding_link', methods: ['GET'])]
    public function link(Request $request, HouseholdRepository $households): RedirectResponse
    {
        $pasted = trim($request->query->getString('lien'));
        $token = 1 === preg_match('#(?:rejoindre/)?([a-f0-9]{32})#i', $pasted, $match) ? strtolower($match[1]) : null;
        if (null === $token || null === $households->findOneBy(['inviteToken' => $token])) {
            $this->addFlash('invite_error', '' === $pasted ? 'Colle le lien que ta coloc t’a envoyé.' : 'Ce lien ne mène à aucune coloc : demande-leur de te le renvoyer.');

            return $this->redirect($this->generateUrl('app_login').'#rejoindre');
        }

        return $this->redirectToRoute('onboarding_join', ['token' => $token]);
    }

    /** "Qui es-tu ?": the profiles waiting to be claimed, or someone new (?nouveau). */
    #[Route('/rejoindre/{token}', name: 'onboarding_join', methods: ['GET', 'POST'])]
    public function join(string $token, HouseholdRepository $households, Request $request, MemberRegistrar $registrar): Response
    {
        $household = $households->findOneBy(['inviteToken' => $token]);
        if (null !== $already = $this->alreadyIn($household)) {
            return $already;
        }
        if (null === $household) {
            return $this->render('onboarding/invalid_link.html.twig', [], new Response(status: Response::HTTP_NOT_FOUND));
        }

        $unclaimed = $household->getUnclaimedMembers();
        if ([] !== $unclaimed && $request->isMethod('GET') && !$request->query->has('nouveau')) {
            return $this->render('onboarding/join_choose.html.twig', ['household' => $household, 'unclaimed' => $unclaimed]);
        }

        $registration = new Registration();
        $form = $this->createForm(RegistrationType::class, $registration)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', \sprintf('Bienvenue dans %s !', $household->getName()));

            return $this->logIn($registrar->register($household, $registration));
        }

        return $this->render('onboarding/join.html.twig', ['form' => $form, 'household' => $household, 'can_choose' => [] !== $unclaimed]);
    }

    /** "Je suis Robin": the profile waiting for them, with everything already attached to it. */
    #[Route('/rejoindre/{token}/profil/{member}', name: 'onboarding_claim', requirements: ['member' => '\d+'], methods: ['GET', 'POST'])]
    public function claim(
        #[MapEntity(mapping: ['token' => 'inviteToken'])] Household $household,
        #[MapEntity(id: 'member')] Member $member,
        Request $request,
        MemberRegistrar $registrar,
    ): Response {
        if (null !== $already = $this->alreadyIn($household)) {
            return $already;
        }
        if (!$member->belongsTo($household)) {
            throw $this->createNotFoundException();
        }
        if ($member->isClaimed()) {
            $this->addFlash('success', \sprintf('Le profil de %s a déjà été réclamé : connecte-toi avec son pseudo.', $member->getName()));

            return $this->redirectToRoute('app_login');
        }

        $registration = new Registration();
        $registration->name = $member->getName();
        $registration->username = $member->getName();
        $form = $this->createForm(RegistrationType::class, $registration, ['with_name' => false])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', \sprintf('Salut %s ! Tout ce qui était à toi t’attendait.', $member->getName()));

            return $this->logIn($registrar->claim($member, $registration));
        }

        return $this->render('onboarding/claim.html.twig', ['form' => $form, 'household' => $household, 'member' => $member]);
    }

    /** Someone already logged in who opens an invitation link: home, saying where they are. */
    private function alreadyIn(?Household $household): ?RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Member) {
            return null;
        }
        $mine = $user->getHousehold();
        $this->addFlash('success', null === $household || $household->getId() === $mine->getId()
            ? \sprintf('Tu es déjà dans %s !', $mine->getName())
            : \sprintf('Tu es dans %s : pour rejoindre %s, déconnecte-toi d’abord (Profil).', $mine->getName(), $household->getName()));

        return $this->redirectToRoute('app_home');
    }

    /** The setting up is only for a household not yet started: once tasks exist, on to inviting; once the tour is done, home. */
    private function setUpAlready(Member $member): ?RedirectResponse
    {
        if ($member->isOnboarded()) {
            return $this->redirectToRoute('app_home');
        }
        if (0 < $this->entityManager->getRepository(Task::class)->count(['household' => $member->getHousehold()])) {
            return $this->redirectToRoute('onboarding_invite');
        }

        return null;
    }

    /** The rooms ticked, and any other typed (commas between them): the others go, they hold nothing yet. */
    private function keepRooms(Household $household, Request $request): void
    {
        $names = array_filter(array_map(trim(...), array_filter($request->request->all('pieces'), 'is_string')));
        foreach (explode(',', $request->request->getString('autre')) as $typed) {
            $typed = trim($typed);
            if ('' !== $typed) {
                $names[] = mb_strtoupper(mb_substr($typed, 0, 1)).mb_substr($typed, 1);
            }
        }
        $wanted = [];
        foreach ($names as $name) {
            $wanted[mb_strtolower($name)] = mb_substr($name, 0, 60);
        }

        foreach ($household->getZones()->toArray() as $zone) {
            if (!isset($wanted[mb_strtolower($zone->getName())])) {
                $household->removeZone($zone);
            } else {
                unset($wanted[mb_strtolower($zone->getName())]);
            }
        }
        foreach ($wanted as $name) {
            new Zone($household, $name);
        }
    }

    /** The animals kept (ticked), and the new ones named, each with its species. */
    private function keepPets(Household $household, Request $request): void
    {
        $kept = array_map('intval', array_filter($request->request->all('garder'), 'is_string'));
        foreach ($household->getPets()->toArray() as $pet) {
            if (!\in_array($pet->getId(), $kept, true)) {
                $household->removePet($pet);
            }
        }
        foreach ($request->request->all('animaux') as $new) {
            $name = \is_array($new) ? trim((string) ($new['nom'] ?? '')) : '';
            if ('' === $name) {
                continue;
            }
            $species = PetSpecies::tryFrom((string) ($new['espece'] ?? '')) ?? PetSpecies::Cat;
            $this->entityManager->persist(new Pet($household, mb_substr($name, 0, 40), $species));
        }
    }

    /** Logged in for a long time, as with the login form (it lives on the phone), then on to what comes next. */
    private function logIn(Member $member, string $next = 'tour_start'): RedirectResponse
    {
        $this->security->login($member, 'form_login', 'main', [new RememberMeBadge()]);

        return $this->redirectToRoute($next);
    }
}
