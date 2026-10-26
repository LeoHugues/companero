<?php

namespace App\Household;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\PetSpecies;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Twig\TaskLabels;

/**
 * What a house starts with, chosen right after it is created (OnboardingController): its rooms, and the chores nearly
 * every coloc shares, each with a sensible rhythm and its points. The usual ones come ticked; all of them change later,
 * like any other task. Those of a room the house does not have, or of an animal it has not, are not offered.
 */
final readonly class StarterKit
{
    /** The rooms offered, the usual ones ticked. */
    public const ROOMS = [
        'Cuisine' => true, 'Salon' => true, 'Salle de bain' => true, 'WC' => true, 'Entrée' => true,
        'Couloir' => false, 'Buanderie' => false, 'Bureau' => false, 'Balcon' => false, 'Terrasse' => false,
        'Jardin' => false, 'Garage' => false, 'Cave' => false,
    ];

    /** The bins go out on Tuesday evening, until told otherwise. */
    private const BINS_DAY = 2;

    /**
     * Each chore: its title, category, points, rhythm (days; "quick"; or a weekday and a time), the rooms it goes
     * through (those the house has), ticked or not, and what it needs: a room, "cat", "dog" or "pet" (any animal).
     *
     * @var array<string, array{string, TaskCategory, int, int|string|array{int, string}, list<string>, bool, ?string}>
     */
    private const TASKS = [
        'aspirateur' => ['Aspirateur et balai', TaskCategory::Cleaning, 20, 7, ['Salon', 'Cuisine', 'Entrée', 'Couloir'], true, null],
        'serpilliere' => ['Serpillière', TaskCategory::Cleaning, 25, 7, ['Cuisine', 'Salle de bain', 'WC', 'Entrée'], true, null],
        'toilettes' => ['Nettoyer les toilettes', TaskCategory::Cleaning, 20, 7, ['WC'], true, null],
        'salle-de-bain' => ['Nettoyer la salle de bain', TaskCategory::Cleaning, 30, 14, ['Salle de bain'], true, 'Salle de bain'],
        'cuisine' => ['Plans de travail et évier', TaskCategory::Cleaning, 10, 3, ['Cuisine'], true, 'Cuisine'],
        'poubelles' => ['Sortir les poubelles', TaskCategory::Cleaning, 10, [self::BINS_DAY, '20:00'], [], true, null],
        'lave-vaisselle' => ['Vider le lave-vaisselle', TaskCategory::Cleaning, 10, 'quick', ['Cuisine'], true, 'Cuisine'],
        'litiere' => ['Changer la litière', TaskCategory::Pets, 10, 2, [], true, 'cat'],
        'nourrir' => ['Nourrir les animaux', TaskCategory::Pets, 5, 'quick', [], true, 'pet'],
        'promener' => ['Promener le chien', TaskCategory::Pets, 10, 'quick', [], true, 'dog'],
        'frigo' => ['Nettoyer le frigo', TaskCategory::Cleaning, 30, 30, ['Cuisine'], false, 'Cuisine'],
        'linge' => ['Changer torchons et serviettes', TaskCategory::Cleaning, 5, 7, ['Cuisine', 'Salle de bain'], false, null],
        'vitres' => ['Nettoyer les vitres', TaskCategory::Cleaning, 50, 60, [], false, null],
        'plantes' => ['Arroser les plantes', TaskCategory::Garden, 5, 4, [], false, null],
        'pelouse' => ['Tondre la pelouse', TaskCategory::Garden, 60, 14, ['Jardin'], false, 'Jardin'],
    ];

    /**
     * The chores offered to this house, in order, with their title as it will be (an only animal is named).
     *
     * @return list<array{key: string, title: string, category: TaskCategory, points: int, rule: string, rooms: list<string>, ticked: bool}>
     */
    public function offer(Household $household): array
    {
        $offer = [];
        foreach (self::TASKS as $key => [$title, $category, $points, $rhythm, $rooms, $ticked, $needs]) {
            if (!$this->has($household, $needs)) {
                continue;
            }
            $offer[] = [
                'key' => $key,
                'title' => $this->title($household, $key, $title),
                'category' => $category,
                'points' => $points,
                'rule' => match (true) {
                    \is_int($rhythm) => \sprintf('tous les %d j', $rhythm),
                    'quick' === $rhythm => 'en un geste',
                    default => \sprintf('le %s soir', TaskLabels::weekday($rhythm[0])),
                },
                'rooms' => array_values(array_filter($rooms, fn (string $room): bool => null !== $this->zone($household, $room))),
                'ticked' => $ticked,
            ];
        }

        return $offer;
    }

    /**
     * The chores chosen, as tasks of the house, new to the database (nothing is persisted).
     *
     * @param list<string> $keys
     *
     * @return list<Task>
     */
    public function apply(Household $household, Member $author, \DateTimeImmutable $now, array $keys): array
    {
        $tasks = [];
        foreach (self::TASKS as $key => [$title, $category, $points, $rhythm, $rooms, , $needs]) {
            if (!\in_array($key, $keys, true) || !$this->has($household, $needs)) {
                continue;
            }
            $task = new Task($household, $author, $now);
            $task->setTitle($this->title($household, $key, $title));
            $task->setCategory($category);
            $task->setPoints($points);
            if (\is_int($rhythm)) {
                $task->setKind(TaskKind::Rolling);
                $task->setRhythmDays($rhythm);
                $task->setFirstDueAt($this->firstDue($household, $now, $rhythm));
            } elseif ('quick' === $rhythm) {
                $task->setKind(TaskKind::Quick);
                $task->setCooldownHours(2);
            } else {
                $task->setKind(TaskKind::Scheduled);
                $task->setScheduledWeekday($rhythm[0]);
                $task->setScheduledTime(new \DateTimeImmutable($rhythm[1]));
                $task->setMarginHours(12);
            }
            foreach ($rooms as $room) {
                if (null !== $zone = $this->zone($household, $room)) {
                    $task->addZone($zone);
                }
            }
            if (null !== $pet = $this->onlyPet($household, $needs)) {
                $task->setPet($pet);
            }
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * When a chore first comes due: the frequent ones at once (the house starts with something to do), the weekly ones
     * on the cleaning day, the rarer ones a week after. All due at once, the Casa would greet a new coloc in her dust.
     */
    private function firstDue(Household $household, \DateTimeImmutable $now, int $rhythm): ?\DateTimeImmutable
    {
        if ($rhythm <= 3) {
            return null;
        }
        $cleaningDay = $now->setTime(9, 0)->modify(\sprintf('+%d days', ($household->getCleaningDay() - (int) $now->format('N') + 7) % 7));

        return $rhythm > 14 ? $cleaningDay->modify('+7 days') : $cleaningDay;
    }

    private function has(Household $household, ?string $needs): bool
    {
        return match ($needs) {
            null => true,
            'pet' => !$household->getPets()->isEmpty(),
            'cat', 'dog' => [] !== $this->pets($household, $needs),
            default => null !== $this->zone($household, $needs),
        };
    }

    /** "Promener Rex", "Nourrir Tishka", "Nourrir les chats": an animal is named when it is the only one. */
    private function title(Household $household, string $key, string $title): string
    {
        $pets = $household->getPets()->toArray();

        return match (true) {
            'promener' === $key && 1 === \count($dogs = $this->pets($household, 'dog')) => 'Promener '.$dogs[0]->getName(),
            'litiere' === $key && 1 === \count($cats = $this->pets($household, 'cat')) => 'Changer la litière de '.$cats[0]->getName(),
            'nourrir' === $key && 1 === \count($pets) => 'Nourrir '.$pets[0]->getName(),
            'nourrir' === $key && \count($pets) === \count($this->pets($household, 'cat')) => 'Nourrir les chats',
            'nourrir' === $key && \count($pets) === \count($this->pets($household, 'dog')) => 'Nourrir les chiens',
            default => $title,
        };
    }

    /** The animal a chore is for, when the house has only one it can be. */
    private function onlyPet(Household $household, ?string $needs): ?Pet
    {
        $pets = match ($needs) {
            'cat', 'dog' => $this->pets($household, $needs),
            'pet' => $household->getPets()->toArray(),
            default => [],
        };

        return 1 === \count($pets) ? array_values($pets)[0] : null;
    }

    /** @return list<Pet> */
    private function pets(Household $household, string $species): array
    {
        $wanted = 'cat' === $species ? PetSpecies::Cat : PetSpecies::Dog;

        return array_values(array_filter($household->getPets()->toArray(), static fn (Pet $pet): bool => $wanted === $pet->getSpecies()));
    }

    private function zone(Household $household, string $name): ?Zone
    {
        foreach ($household->getZones() as $zone) {
            if (0 === strcasecmp($zone->getName(), $name)) {
                return $zone;
            }
        }

        return null;
    }
}
