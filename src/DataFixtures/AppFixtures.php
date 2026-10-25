<?php

namespace App\DataFixtures;

use App\Bounty\WeekPlanner;
use App\Calendar\Week;
use App\Entity\Gift;
use App\Entity\Household;
use App\Entity\Ledger;
use App\Entity\Member;
use App\Entity\Participant;
use App\Entity\Pet;
use App\Entity\Presence;
use App\Entity\ShoppingItem;
use App\Entity\Task;
use App\Entity\YellowCard;
use App\Entity\Zone;
use App\Enum\GiftKind;
use App\Enum\PetSpecies;
use App\Enum\SplitMode;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Household\Blueprint;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use App\Ledger\ExpenseDraft;
use App\Ledger\ExpenseRecorder;
use App\Ledger\LedgerBook;
use App\Repository\CompletionRepository;
use App\Repository\TaskRepository;
use App\Review\WeekCloser;
use App\Task\TaskCompleter;
use App\Task\TaskStatusResolver;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Our coloc, as it is these days: Léo, Léa, Robin and Gab, two cats, the cleaning on Sunday,
 * a few weeks of history and last Sunday's big clean. Log in as leo@example.com / companero.
 */
final class AppFixtures extends Fixture
{
    public const PASSWORD = 'companero';
    private const HISTORY_WEEKS = 3;
    private const SUNDAY = 7;

    /** Zones added to the founder's defaults (Cuisine, Salon, Salle de bain, WC; not its Entrée); true: private. */
    private const ZONES = [
        'Bureau' => false,
        'Couloir' => false,
        'Grande terrasse' => false,
        'Petite terrasse' => false,
        'Terrasse de l’entrée' => false,
        'Jardin' => false,
        'Chambre de Léo et Léa' => true,
        'Chambre de Robin' => true,
        'Chambre de Gab' => true,
        'Salle de bain de Gab' => true,
    ];

    /**
     * The Sunday package: every week, ideally on the cleaning day.
     * [title, zone, points, rhythm in days, weekly commitment].
     */
    private const CLEANING_DAY = [
        ['Aspirateur salon et cuisine', 'Salon', 20, 7, 1],
        ['Aspirateur du bureau', 'Bureau', 10, 7, 1],
        ['Aspirateur du couloir', 'Couloir', 10, 7, 1],
        ['Aspirateur de la salle de bain', 'Salle de bain', 10, 7, 1],
        ['Plans de travail (javel ou vinaigre)', 'Cuisine', 25, 7, 1],
        ['Nettoyer les toilettes', 'WC', 20, 7, 1],
        ['Serpillière salon, cuisine et toilettes', 'Salon', 30, 7, 1],
        ['Balayer la grande terrasse', 'Grande terrasse', 20, 7, 1],
        ['Balayer la petite terrasse', 'Petite terrasse', 10, 7, 1],
        ['Balayer la terrasse de l’entrée', 'Terrasse de l’entrée', 10, 7, 1],
        ['Nettoyer la grande table de la terrasse', 'Grande terrasse', 10, 7, null],
        ['Aspirateur de la chambre', 'Chambre de Léo et Léa', 10, 7, null],
        ['Aspirateur de la chambre de Robin', 'Chambre de Robin', 10, 7, null],
        ['Aspirateur de la chambre de Gab', 'Chambre de Gab', 10, 7, null],
    ];

    /**
     * Less frequent jobs: [title, zone, points, rhythm in days, category, days since last done (null: never)].
     */
    private const OCCASIONAL = [
        ['Tondre autour de la maison', 'Jardin', 30, 14, TaskCategory::Garden, 20],
        ['Nettoyer les vitres du salon', 'Salon', 50, 60, TaskCategory::Cleaning, 40],
        ['Nettoyer et ranger le tiroir à couverts', 'Cuisine', 20, 30, TaskCategory::Cleaning, null],
        ['Nettoyer sous le lave-vaisselle', 'Cuisine', 20, 30, TaskCategory::Cleaning, 25],
        ['Nettoyer le lave-linge', 'Salle de bain', 20, 30, TaskCategory::Cleaning, 12],
        ['Ranger le placard du couloir', 'Couloir', 30, 60, TaskCategory::Cleaning, 50],
        ['Ranger le placard de l’entrée', 'Salon', 30, 60, TaskCategory::Cleaning, null],
    ];

    /** Done when needed, in one tap: [title, zone, points, chance of being done on a given day, cooldown in hours]. */
    private const QUICK = [
        ['Vider le lave-vaisselle', 'Cuisine', 10, 70, 6],
        ['Ranger la vaisselle de l’égouttoir', 'Cuisine', 5, 50, 3],
        ['Faire le verre', 'Salon', 10, 12, 48],
    ];

    /** @var array<string, Task> */
    private array $tasks = [];

    public function __construct(
        private readonly HouseholdFounder $founder,
        private readonly MemberRegistrar $registrar,
        private readonly TaskCompleter $completer,
        private readonly TaskStatusResolver $resolver,
        private readonly CompletionRepository $completions,
        private readonly WeekCloser $weekCloser,
        private readonly TaskRepository $taskRepository,
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(41);
        $now = $this->clock->now();
        $start = Week::containing($now)->start->modify(\sprintf('-%d weeks', self::HISTORY_WEEKS));
        $lastSunday = $this->lastSunday($now);

        try {
            // The household was founded when its history starts.
            Clock::set(new MockClock($start));
            $leo = $this->founder->found($this->founding());
            $household = $leo->getHousehold();
            [$lea, $robin, $gab] = array_map(fn (string $name): Member => $this->registrar->register($household, $this->registration($name)), ['Léa', 'Robin', 'Gab']);
            $members = ['Léo' => $leo, 'Léa' => $lea, 'Robin' => $robin, 'Gab' => $gab];

            $zones = $this->zones($household, $manager);
            [$tishka] = $this->pets($household, $manager);
            $this->createTasks($household, $leo, $zones, $tishka, $start, $now, $manager);

            // Léa has been away since the week of last Sunday: 0 days a week.
            $manager->persist(new Presence($lea, Week::containing($lastSunday)->start, 0));
            $manager->flush();

            $this->replay($start, $lastSunday, $members, $lastSunday);
            $this->bigCleanOn($lastSunday, $household, $members, $zones, $manager);
            $this->replay($lastSunday->modify('+1 day'), $now, $members, $lastSunday);
        } finally {
            Clock::set(new NativeClock());
        }

        $lea->setAtHome(false, $lastSunday);
        // The tour is behind them (it can be read again from the profile), the charter agreed to.
        foreach ($members as $member) {
            $member->finishOnboarding($start);
            $member->acceptCharter($start);
        }
        // When it happens (after the history, which they are no part of): Gizmo was sick this morning; the flush is fine for now.
        $sick = new Task($household, $leo, $start);
        $sick->setTitle('Nettoyer le vomi de Gizmo');
        $sick->setKind(TaskKind::Occasional);
        $sick->setCategory(TaskCategory::Pets);
        $sick->setPoints(15);
        $sick->setZone($zones['Salon']);
        $sick->raise(min($now, $now->setTime(8, 30)));
        $flush = new Task($household, $leo, $start);
        $flush->setTitle('Réparer la chasse d’eau');
        $flush->setKind(TaskKind::Occasional);
        $flush->setCategory(TaskCategory::Repair);
        $flush->setPoints(40);
        $flush->setZone($zones['WC']);
        $manager->persist($sick);
        $manager->persist($flush);
        $manager->flush();

        for ($week = Week::containing($start); $week->end() <= $now; $week = $week->next()) {
            $this->weekCloser->close($household, $week);
        }

        // This week's surprises, always the same ones; a yellow card for Léo to give, one Robin gave him.
        (new WeekPlanner($manager, $this->taskRepository, new Randomizer(new Mt19937(41))))->plan($household, Week::containing($now));
        $manager->persist(new Gift($leo, GiftKind::YellowCard, 'Niveau 3', $now->modify('-1 day')));
        $manager->persist(new YellowCard($robin, $leo, 'Le vélo en plein milieu de l’entrée', $now->modify('-3 hours')));
        $this->shopping($household, $members, $now, $manager);
        $manager->flush();
        \assert($manager instanceof EntityManagerInterface);
        $this->accounts($household, $members, $now, $manager);
    }

    /**
     * The accounts: the coloc's common expenses these last two weeks (the electricity, the internet box, the groceries,
     * Friday's pizzas without Gab, the house-warming drinks where Robin brought a friend, Gab paying Léo back), and last
     * month's weekend in Annecy with Camille, a friend, who paid the fondue in Geneva in Swiss francs.
     *
     * @param array<string, Member> $members
     */
    private function accounts(Household $household, array $members, \DateTimeImmutable $now, EntityManagerInterface $manager): void
    {
        $common = new Ledger($household, LedgerBook::COMMON_NAME, $now->modify('-15 days'));
        $common->setEveryone(true);
        $common->welcome($household->getMembers());
        $annecy = new Ledger($household, 'Week-end à Annecy', $now->modify('-32 days'));
        $annecy->setDescription('Le chalet au bord du lac, avec Camille');
        foreach (['Léo', 'Léa', 'Robin'] as $name) {
            $annecy->addParticipant($name, $members[$name]);
        }
        $annecy->addParticipant('Camille');
        $manager->persist($common);
        $manager->persist($annecy);
        $manager->flush();

        // [title, cents, who paid, for whom (by parts: name => parts), when], noted by who paid.
        $everyone = ['Léo', 'Léa', 'Robin', 'Gab'];
        foreach ([
            ['Électricité EDF', 8420, 'Léo', $everyone, '-12 days 19:10'],
            ['Internet Free', 2999, 'Gab', $everyone, '-9 days 08:30'],
            ['Courses Lidl', 4230, 'Robin', $everyone, '-6 days 18:45'],
            ['Pizzas du vendredi', 3600, 'Léa', ['Léo', 'Léa', 'Robin'], '-4 days 21:15'],
            ['Produits ménagers', 1860, 'Léo', $everyone, '-3 days 11:00'],
            ['Apéro de la crémaillère', 4800, 'Robin', ['Léo' => 1, 'Léa' => 1, 'Robin' => 2, 'Gab' => 1], '-2 days 22:30'],
            ['Courses du marché', 2340, 'Robin', $everyone, '-1 hour'],
        ] as [$title, $amount, $payer, $for, $when]) {
            $this->spend($common, $title, $amount, $payer, $for, $members[$payer], $now->modify($when), $manager);
        }
        $back = ExpenseDraft::transfer($common, self::participant($common, 'Gab'), self::participant($common, 'Léo'), 2000, $now->modify('-1 day'));
        (new ExpenseRecorder($manager, new MockClock($now->modify('-1 day 09:00'))))->record($back, $members['Gab']);

        $weekend = ['Léo', 'Léa', 'Robin', 'Camille'];
        $this->spend($annecy, 'Location du chalet', 36000, 'Léa', $weekend, $members['Léa'], $now->modify('-30 days 20:00'), $manager);
        $this->spend($annecy, 'Courses du week-end', 6480, 'Léo', $weekend, $members['Léo'], $now->modify('-29 days 10:30'), $manager);
        $this->spend($annecy, 'Fondue à Genève', 9600, 'Camille', $weekend, $members['Léo'], $now->modify('-29 days 21:00'), $manager, ['CHF', '1.06']);
        $this->spend($annecy, 'Essence', 5200, 'Robin', ['Léo', 'Léa', 'Robin'], $members['Robin'], $now->modify('-28 days 18:00'), $manager);
    }

    /**
     * @param list<string>|array<string, int> $for         names, or names => parts
     * @param array{string, string}|null      $foreignRate paid in another currency, at a rate
     */
    private function spend(Ledger $ledger, string $title, int $amount, string $payer, array $for, Member $by, \DateTimeImmutable $at, EntityManagerInterface $manager, ?array $foreignRate = null): void
    {
        $draft = ExpenseDraft::new($ledger, self::participant($ledger, $payer), $at);
        $draft->title = $title;
        $draft->amount = $amount;
        if (null !== $foreignRate) {
            [$draft->currency, $draft->exchangeRate] = $foreignRate;
        }
        $parts = array_is_list($for) ? array_fill_keys($for, 1) : $for;
        $draft->splitMode = array_is_list($for) ? SplitMode::Equal : SplitMode::Shares;
        foreach ($ledger->getParticipants() as $participant) {
            $draft->split[(int) $participant->getId()]->included = isset($parts[$participant->getName()]);
            $draft->split[(int) $participant->getId()]->weight = $parts[$participant->getName()] ?? 1;
        }
        (new ExpenseRecorder($manager, new MockClock($at)))->record($draft, $by);
    }

    private static function participant(Ledger $ledger, string $name): Participant
    {
        return $ledger->getParticipants()->findFirst(static fn (int $key, Participant $participant): bool => $participant->getName() === $name) ?? throw new \LogicException($name.' is not in '.$ledger->getName().'.');
    }

    /**
     * What the house is missing: no more toilet paper since this morning, the sponges are done, the corridor's bulb
     * blew; the washing-up liquid and the kibble were just bought, the bin bags a while ago.
     *
     * @param array<string, Member> $members
     */
    private function shopping(Household $household, array $members, \DateTimeImmutable $now, ObjectManager $manager): void
    {
        $bought = ['Liquide vaisselle' => ['Robin', '-2 days'], 'Croquettes' => ['Léa', '-5 days'], 'Sacs poubelle' => ['Gab', '-12 days'], 'Papier toilette' => ['Léo', '-9 days']];
        $missing = ['Ampoule du couloir' => ['Léo', '-1 day', false], 'Éponges' => ['Robin', '-2 days', false], 'Papier toilette' => ['Gab', '-3 hours', true]];
        $items = [];
        foreach ($bought as $name => [$by, $when]) {
            $items[$name] = new ShoppingItem($household, $name);
            $items[$name]->notice($members[$by], $now->modify($when.' -1 day'));
            $items[$name]->buy($members[$by], $now->modify($when));
        }
        foreach ($missing as $name => [$by, $when, $urgent]) {
            $items[$name] ??= new ShoppingItem($household, $name);
            $items[$name]->notice($members[$by], $now->modify($when), $urgent);
        }
        foreach ($items as $item) {
            $manager->persist($item);
        }
    }

    private function founding(): Founding
    {
        $founding = new Founding();
        $founding->householdName = 'La coloc';
        $founding->cleaningDay = self::SUNDAY;
        $founding->name = 'Léo';
        $founding->username = 'leo';
        $founding->plainPassword = self::PASSWORD;

        return $founding;
    }

    private function registration(string $name): Registration
    {
        $registration = new Registration();
        $registration->name = $name;
        $registration->username = str_replace('é', 'e', $name);
        $registration->plainPassword = self::PASSWORD;

        return $registration;
    }

    /** @return array<string, Zone> */
    private function zones(Household $household, ObjectManager $manager): array
    {
        $zones = [];
        foreach ($household->getZones() as $zone) {
            // No entrance here: it is part of the living room.
            if ('Entrée' === $zone->getName()) {
                $household->removeZone($zone);
                continue;
            }
            $zones[$zone->getName()] = $zone;
        }
        foreach (self::ZONES as $name => $private) {
            $zones[$name] = new Zone($household, $name, $private);
            $manager->persist($zones[$name]);
        }
        // The plan of the house, as described for production.
        foreach (Blueprint::fromFile($this->projectDir.'/config/coloc/notre-coloc.yaml')->plan() as $name => $shape) {
            $zones[$name]->setPlanShape($shape);
        }

        return $zones;
    }

    /** @return list<Pet> */
    private function pets(Household $household, ObjectManager $manager): array
    {
        $pets = [
            new Pet($household, 'Tishka', PetSpecies::Cat, 'Chat roux, petit et mince, à poils longs'),
            // His name is still to be filled in.
            new Pet($household, 'Gizmo', PetSpecies::Cat, 'Gros chat, presque un maine coon : brun foncé tigré, plus clair vers le ventre'),
        ];
        foreach ($pets as $pet) {
            $manager->persist($pet);
        }

        return $pets;
    }

    /** @param array<string, Zone> $zones */
    private function createTasks(Household $household, Member $author, array $zones, Pet $tishka, \DateTimeImmutable $createdAt, \DateTimeImmutable $now, ObjectManager $manager): void
    {
        foreach (self::CLEANING_DAY as [$title, $zone, $points, $rhythm, $commitment]) {
            $task = $this->task($household, $author, $createdAt, $title, TaskKind::Rolling, $points, $zones[$zone]);
            $task->setRhythmDays($rhythm);
            $task->setWeeklyCommitment($commitment);
            // The living room's vacuum and mop go through the open kitchen and its toilets too.
            if ('Salon' === $zone && str_contains($title, 'cuisine')) {
                $task->addZone($zones['Cuisine']);
                $task->addZone($zones['WC']);
            }
            // Done the Sunday before the history starts.
            $task->complete($createdAt->modify('-1 day')->setTime(11, 0));
        }

        foreach (self::OCCASIONAL as [$title, $zone, $points, $rhythm, $category, $doneDaysAgo]) {
            $task = $this->task($household, $author, $createdAt, $title, TaskKind::Rolling, $points, $zones[$zone], $category);
            $task->setRhythmDays($rhythm);
            if (null !== $doneDaysAgo) {
                $task->complete($now->modify(\sprintf('-%d days', $doneDaysAgo))->setTime(15, 0));
            }
        }

        foreach (self::QUICK as [$title, $zone, $points, , $cooldown]) {
            $this->task($household, $author, $createdAt, $title, TaskKind::Quick, $points, $zones[$zone])->setCooldownHours($cooldown);
        }

        $bins = $this->task($household, $author, $createdAt, 'Sortir les poubelles', TaskKind::Scheduled, 10, $zones['Salon']);
        $bins->setScheduledWeekday(self::SUNDAY);
        $bins->setScheduledTime(new \DateTimeImmutable('20:00'));
        $bins->setMarginHours(12);

        $food = $this->task($household, $author, $createdAt, 'Nourrir les chats', TaskKind::Scheduled, 5, null, TaskCategory::Pets);
        $food->setScheduledWeekday(Task::EVERY_DAY);
        $food->setScheduledTime(new \DateTimeImmutable('19:00'));
        $food->setMarginHours(3);

        $litter = $this->task($household, $author, $createdAt, 'Changer la litière', TaskKind::Rolling, 10, null, TaskCategory::Pets);
        $litter->setRhythmDays(2);
        $litter->setMarginHours(12);
        $litter->setAssignee($author);

        $brush = $this->task($household, $author, $createdAt, 'Brosser Tishka', TaskKind::Rolling, 10, null, TaskCategory::Pets);
        $brush->setPet($tishka);
        $brush->setRhythmDays(7);

        foreach ($this->tasks as $task) {
            $manager->persist($task);
        }
        $manager->flush();
    }

    private function task(Household $household, Member $author, \DateTimeImmutable $createdAt, string $title, TaskKind $kind, int $points, ?Zone $zone, TaskCategory $category = TaskCategory::Cleaning): Task
    {
        $task = new Task($household, $author, $createdAt);
        $task->setTitle($title);
        $task->setKind($kind);
        $task->setPoints($points);
        $task->setZone($zone);
        $task->setCategory($category);

        return $this->tasks[$title] = $task;
    }

    /**
     * Last Sunday's big clean, as it happened: Léo did the whole Sunday package, the bins and
     * fixed the toilets; Gab mowed around the house; Robin emptied the dishwasher.
     *
     * @param array<string, Member> $members
     * @param array<string, Zone>   $zones
     */
    private function bigCleanOn(\DateTimeImmutable $sunday, Household $household, array $members, array $zones, ObjectManager $manager): void
    {
        $at = $sunday->setTime(10, 0);
        foreach (self::CLEANING_DAY as [$title]) {
            if (!\in_array($title, ['Aspirateur de la chambre de Robin', 'Aspirateur de la chambre de Gab'], true)) {
                $this->doAt($title, $members['Léo'], $at = $at->modify('+12 minutes'));
            }
        }

        $repair = $this->task($household, $members['Léo'], $sunday->setTime(9, 30), 'Réparer les toilettes', TaskKind::OneOff, 20, $zones['WC'], TaskCategory::Repair);
        $manager->persist($repair);
        $manager->flush();
        $this->doAt('Réparer les toilettes', $members['Léo'], $sunday->setTime(13, 40));

        $this->doAt('Vider le lave-vaisselle', $members['Robin'], $sunday->setTime(12, 15));
        $this->doAt('Tondre autour de la maison', $members['Gab'], $sunday->setTime(15, 30));
        $this->doAt('Nourrir les chats', $members['Robin'], $sunday->setTime(19, 5));
        $this->doAt('Sortir les poubelles', $members['Léo'], $sunday->setTime(19, 45));
    }

    /**
     * Replays the life of the household day after day, in [$from, $to): pressing tasks usually
     * get done, the others now and then; nobody touches the Sunday package in the week of the
     * big clean, done all at once on Sunday.
     *
     * @param array<string, Member> $members
     */
    private function replay(\DateTimeImmutable $from, \DateTimeImmutable $to, array $members, \DateTimeImmutable $bigClean): void
    {
        $sundayPackage = array_column(self::CLEANING_DAY, 4, 0);
        $chances = array_column(self::QUICK, 3, 0);

        for ($day = $from->setTime(0, 0); $day < $to; $day = $day->modify('+1 day')) {
            $week = Week::containing($day);
            $present = array_values(array_filter($members, static fn (Member $m): bool => 'Léa' !== $m->getName() || $week->end() <= Week::containing($bigClean)->start));

            foreach ($this->tasks as $title => $task) {
                if ($task->isArchived() || $task->getCreatedAt() > $day->setTime(23, 59)) {
                    continue;
                }
                $at = $day->setTime(mt_rand(8, 21), mt_rand(0, 59));
                if ('Nourrir les chats' === $title) {
                    $at = $day->setTime(18, mt_rand(40, 59));
                }
                if ($at >= $to) {
                    continue;
                }
                if (isset($sundayPackage[$title]) && $week->contains($bigClean)) {
                    continue;
                }

                if (mt_rand(1, 100) > $this->chance($task, $at, $chances[$title] ?? null, $week)) {
                    continue;
                }
                $this->doAt($title, $this->whoDoes($task, $present), $at);
            }
        }
    }

    /** In percent. */
    private function chance(Task $task, \DateTimeImmutable $at, ?int $quickChance, Week $week): int
    {
        if (TaskKind::Quick === $task->getKind()) {
            return (int) $quickChance;
        }
        if ('Nourrir les chats' === $task->getTitle()) {
            return 95;
        }

        $status = $this->resolver->resolve($task, $at, self::SUNDAY, $this->completions->countForTask($task, $week->start, $week->end()));
        $occasional = ($task->getRhythmDays() ?? 0) >= 30;

        return match (true) {
            $status->urgency->isPressing() && self::SUNDAY === (int) $at->format('N') => 85,
            $status->urgency->isPressing() => $occasional ? 4 : 35,
            default => $occasional ? 0 : 4,
        };
    }

    private function doAt(string $title, Member $member, \DateTimeImmutable $at): void
    {
        Clock::set(new MockClock($at));
        $this->completer->complete($this->tasks[$title], $member);
    }

    /** @param list<Member> $present */
    private function whoDoes(Task $task, array $present): Member
    {
        $byName = array_column(array_map(static fn (Member $m): array => [$m->getName(), $m], $present), 1, 0);

        return match (true) {
            null !== $task->getAssignee() => $task->getAssignee(),
            'Chambre de Robin' === ($task->getZones()->first() ?: null)?->getName() => $byName['Robin'],
            'Chambre de Gab' === ($task->getZones()->first() ?: null)?->getName() => $byName['Gab'],
            'Chambre de Léo et Léa' === ($task->getZones()->first() ?: null)?->getName() => isset($byName['Léa']) && mt_rand(0, 1) ? $byName['Léa'] : $byName['Léo'],
            default => $present[mt_rand(0, \count($present) - 1)],
        };
    }

    /** The latest Sunday before today. */
    private function lastSunday(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $day = $now->setTime(0, 0)->modify('-1 day');
        while (self::SUNDAY !== (int) $day->format('N')) {
            $day = $day->modify('-1 day');
        }

        return $day;
    }
}
