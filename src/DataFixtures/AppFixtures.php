<?php

namespace App\DataFixtures;

use App\Calendar\Week;
use App\Entity\Absence;
use App\Entity\Completion;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\PointReason;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use App\Review\WeekCloser;
use App\Task\BonusPolicy;
use App\Task\TaskStatusResolver;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * A demo household with a few weeks of history: log in as leo@example.com / companero.
 */
final class AppFixtures extends Fixture
{
    public const PASSWORD = 'companero';
    private const HISTORY_WEEKS = 3;

    public function __construct(
        private readonly HouseholdFounder $founder,
        private readonly MemberRegistrar $registrar,
        private readonly TaskStatusResolver $resolver,
        private readonly BonusPolicy $bonusPolicy,
        private readonly WeekCloser $weekCloser,
        private readonly ClockInterface $clock,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(41);
        $now = $this->clock->now();
        $start = Week::containing($now)->start->modify(\sprintf('-%d weeks', self::HISTORY_WEEKS));

        // The household was founded when its history starts.
        Clock::set(new MockClock($start));
        try {
            $leo = $this->founder->found($this->founding());
        } finally {
            Clock::set(new NativeClock());
        }
        $household = $leo->getHousehold();
        $members = [$leo, ...array_map(fn (string $name): Member => $this->registrar->register($household, $this->registration($name)), ['Inès', 'Max', 'Sam'])];
        foreach ($members as $member) {
            $member->setWeeklyGoal(150);
        }

        $zones = $this->zones($household, $manager);
        $tasks = $this->tasks($household, $leo, $zones, $start, $manager);

        $this->history($tasks, $members, $start, $now, $manager);
        $this->oneOffTasks($household, $members, $now, $manager);
        $this->absence($members[2], $now, $manager);
        $manager->flush();

        for ($week = Week::containing($start); $week->end() <= $now; $week = $week->next()) {
            $this->weekCloser->close($household, $week);
        }
    }

    private function founding(): Founding
    {
        $founding = new Founding();
        $founding->householdName = 'La coloc des Lilas';
        $founding->cleaningDay = 6;
        $founding->name = 'Léo';
        $founding->email = 'leo@example.com';
        $founding->plainPassword = self::PASSWORD;

        return $founding;
    }

    private function registration(string $name): Registration
    {
        $registration = new Registration();
        $registration->name = $name;
        $registration->email = strtolower(str_replace('è', 'e', $name)).'@example.com';
        $registration->plainPassword = self::PASSWORD;

        return $registration;
    }

    /** @return array<string, Zone> */
    private function zones(Household $household, ObjectManager $manager): array
    {
        $zones = [];
        foreach ($household->getZones() as $zone) {
            $zones[$zone->getName()] = $zone;
        }
        $zones['Salle de bain du haut'] = $zones['Salle de bain'];
        $zones['Salle de bain du haut']->setName('Salle de bain du haut');
        foreach (['Jardin' => false, 'Salle de bain de Sam' => true, 'Chambre de Léo' => true] as $name => $private) {
            $zones[$name] = new Zone($household, $name, $private);
            $manager->persist($zones[$name]);
        }

        return $zones;
    }

    /**
     * @param array<string, Zone> $zones
     *
     * @return list<Task>
     */
    private function tasks(Household $household, Member $author, array $zones, \DateTimeImmutable $createdAt, ObjectManager $manager): array
    {
        $definitions = [
            ['Passer l’aspirateur', 'Salon', 30, 3, 2],
            ['Serpillière', 'Salon', 30, 7, 1],
            ['Nettoyer les WC', 'WC', 3, 7, 1],
            ['Nettoyer le plan de travail', 'Cuisine', 1, 2, null],
            ['Nettoyer la salle de bain', 'Salle de bain du haut', 4, 7, 1],
            ['Nettoyer sa salle de bain', 'Salle de bain de Sam', 4, 7, null],
            ['Ranger ma chambre', 'Chambre de Léo', 2, 7, null],
            ['Arroser les plantes', 'Jardin', 1, 4, null],
            ['Faire la vaisselle', 'Cuisine', 2, 1, null],
            ['Nettoyer le frigo', 'Cuisine', 4, 14, null],
            ['Passer l’aspirateur dans l’entrée', 'Entrée', 2, 7, null],
            ['Changer les serviettes', 'Salle de bain du haut', 1, 7, null],
        ];

        $tasks = [];
        foreach ($definitions as [$title, $zone, $points, $rhythm, $commitment]) {
            $task = new Task($household, $author, $createdAt);
            $task->setTitle($title);
            $task->setKind(TaskKind::Rolling);
            $task->setZone($zones[$zone]);
            $task->setPoints($points);
            $task->setRhythmDays($rhythm);
            $task->setWeeklyCommitment($commitment);
            $manager->persist($task);
            $tasks[] = $task;
        }

        $bins = new Task($household, $author, $createdAt);
        $bins->setTitle('Sortir les poubelles');
        $bins->setKind(TaskKind::Scheduled);
        $bins->setZone($zones['Cuisine']);
        $bins->setPoints(1);
        $bins->setScheduledWeekday(2);
        $bins->setScheduledTime(new \DateTimeImmutable('20:00'));
        $bins->setMarginHours(2);
        $manager->persist($bins);
        $tasks[] = $bins;

        return $tasks;
    }

    /**
     * Replays a few weeks of life in the household, day after day.
     *
     * @param list<Task>   $tasks
     * @param list<Member> $members
     */
    private function history(array $tasks, array $members, \DateTimeImmutable $from, \DateTimeImmutable $now, ObjectManager $manager): void
    {
        $doneThisWeek = [];
        for ($day = $from; $day < $now->setTime(0, 0); $day = $day->modify('+1 day')) {
            if ('1' === $day->format('N')) {
                $doneThisWeek = [];
            }
            foreach ($tasks as $index => $task) {
                $at = $day->setTime(mt_rand(8, 21), mt_rand(0, 59));
                $status = $this->resolver->resolve($task, $at, $task->getHousehold()->getCleaningDay(), $doneThisWeek[$index] ?? 0);
                // Pressing tasks usually get done; the others only now and then.
                if (mt_rand(1, 100) > ($status->urgency->isPressing() ? 75 : 10)) {
                    continue;
                }

                $member = $this->whoDoes($task, $members);
                $completion = new Completion($task, $member, $at, $status->urgency);
                $manager->persist($completion);
                $manager->persist(new PointEntry($member, PointReason::Task, $task->getPoints(), $task->getTitle(), $at, $completion));
                if (null !== $bonus = $this->bonusPolicy->bonusFor($task, $status)) {
                    $manager->persist(new PointEntry($member, $bonus->reason, $bonus->points, $task->getTitle(), $at, $completion));
                }
                $task->complete($at);
                $doneThisWeek[$index] = ($doneThisWeek[$index] ?? 0) + 1;
            }
        }
    }

    /** @param list<Member> $members */
    private function whoDoes(Task $task, array $members): Member
    {
        return match ($task->getZone()?->getName()) {
            'Chambre de Léo' => $members[0],
            'Salle de bain de Sam' => $members[3],
            default => $members[mt_rand(0, \count($members) - 1)],
        };
    }

    /** @param list<Member> $members */
    private function oneOffTasks(Household $household, array $members, \DateTimeImmutable $now, ObjectManager $manager): void
    {
        $call = new Task($household, $members[2], $now->modify('-2 days'));
        $call->setTitle('Appeler le proprio pour la fuite du jardin');
        $call->setCategory(TaskCategory::Other);
        $call->setPoints(2);
        $call->setDueAt($now->modify('+3 days')->setTime(18, 0));
        $manager->persist($call);

        $paper = new Task($household, $members[1], $now->modify('-1 day'));
        $paper->setTitle('Racheter du papier toilette');
        $paper->setCategory(TaskCategory::Shopping);
        $paper->setPoints(1);
        $paper->reserveFor($members[1], $now->modify('+20 hours'));
        $manager->persist($paper);
    }

    private function absence(Member $member, \DateTimeImmutable $now, ObjectManager $manager): void
    {
        $absence = new Absence($member);
        $absence->setLabel('Week-end chez mes parents');
        $absence->setStartsOn($now->modify('+9 days')->setTime(0, 0));
        $absence->setEndsOn($now->modify('+11 days')->setTime(0, 0));
        $manager->persist($absence);
    }
}
