<?php

namespace App\Household;

use App\Entity\CatalogItem;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\PetSpecies;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Twig\TaskLabels;
use Symfony\Component\Yaml\Yaml;

/**
 * A household described in a YAML file (see config/coloc/notre-coloc.yaml): its zones and their
 * place on the plan, its pets, its tasks and its catalogue, ready to be created in one go.
 */
final readonly class Blueprint
{
    private const CATEGORIES = [
        'menage' => TaskCategory::Cleaning,
        'ménage' => TaskCategory::Cleaning,
        'bricolage' => TaskCategory::Repair,
        'jardin' => TaskCategory::Garden,
        'course' => TaskCategory::Shopping,
        'animaux' => TaskCategory::Pets,
        'autre' => TaskCategory::Other,
    ];

    private const SPECIES = ['chat' => PetSpecies::Cat, 'chien' => PetSpecies::Dog, 'autre' => PetSpecies::Other];

    /** @param array<string, mixed> $data */
    private function __construct(
        private array $data,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Fichier introuvable : %s', $path));
        }
        $data = Yaml::parseFile($path);
        if (!\is_array($data) || !\is_array($data['coloc'] ?? null)) {
            throw new \InvalidArgumentException('Le fichier doit commencer par une section « coloc ».');
        }

        return new self($data);
    }

    public function name(): string
    {
        return (string) ($this->data['coloc']['nom'] ?? 'La coloc');
    }

    /** ISO-8601 day of the week. */
    public function cleaningDay(): int
    {
        $day = $this->data['coloc']['jour_de_menage'] ?? Household::DEFAULT_CLEANING_DAY;
        if (\is_int($day)) {
            return $day;
        }
        foreach (range(1, 7) as $iso) {
            if (0 === strcasecmp(TaskLabels::weekday($iso), (string) $day)) {
                return $iso;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Jour de ménage inconnu : %s', $day));
    }

    /** @return array<string, string> plan shape of each zone, by zone name */
    public function plan(): array
    {
        $plan = [];
        foreach ($this->list('zones') as $zone) {
            if (isset($zone['plan'])) {
                $plan[(string) $zone['nom']] = (string) $zone['plan'];
            }
        }

        return $plan;
    }

    /**
     * Fills a freshly founded household. Nothing is persisted: the returned entities are.
     *
     * @return list<object>
     */
    public function applyTo(Household $household, Member $author, \DateTimeImmutable $now): array
    {
        $household->setCleaningDay($this->cleaningDay());
        if (isset($this->data['coloc']['objectif_de_la_maison'])) {
            $household->setWeeklyGoal((int) $this->data['coloc']['objectif_de_la_maison']);
        }

        $created = [];
        $zones = [];
        foreach ($this->list('zones') as $definition) {
            $zone = new Zone($household, (string) $definition['nom'], (bool) ($definition['privee'] ?? false));
            $zone->setPlanShape(isset($definition['plan']) ? (string) $definition['plan'] : null);
            $created[] = $zones[$zone->getName()] = $zone;
        }

        foreach ($this->list('animaux') as $definition) {
            $created[] = new Pet(
                $household,
                (string) $definition['nom'],
                self::SPECIES[$definition['espece'] ?? 'chat'] ?? throw new \InvalidArgumentException(\sprintf('Espèce inconnue : %s', $definition['espece'])),
                isset($definition['description']) ? (string) $definition['description'] : null,
            );
        }

        foreach ($this->list('taches') as $definition) {
            $task = $this->task($household, $author, $now, $definition, TaskKind::Rolling, $zones);
            $task->setRhythmDays((int) ($definition['tous_les'] ?? throw new \InvalidArgumentException(\sprintf('« %s » : tous les combien de jours ?', $task->getTitle()))));
            $task->setWeeklyCommitment(isset($definition['par_semaine']) ? (int) $definition['par_semaine'] : null);
            $created[] = $task;
        }

        foreach ($this->list('express') as $definition) {
            $task = $this->task($household, $author, $now, $definition, TaskKind::Quick, $zones);
            $task->setCooldownHours(isset($definition['pas_avant_heures']) ? (int) $definition['pas_avant_heures'] : null);
            $created[] = $task;
        }

        foreach ($this->list('occasionnelles') as $definition) {
            $created[] = $this->task($household, $author, $now, $definition, TaskKind::Occasional, $zones);
        }

        foreach ($this->list('catalogue') as $definition) {
            $created[] = new CatalogItem($household, (string) $definition['titre'], $this->category($definition), (int) $definition['points']);
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, Zone>  $zones
     */
    private function task(Household $household, Member $author, \DateTimeImmutable $now, array $definition, TaskKind $kind, array $zones): Task
    {
        $task = new Task($household, $author, $now);
        $task->setTitle((string) $definition['titre']);
        $task->setKind($kind);
        $task->setCategory($this->category($definition));
        $task->setPoints((int) ($definition['points'] ?? throw new \InvalidArgumentException(\sprintf('« %s » : combien de points ?', $definition['titre']))));
        if (isset($definition['zone'])) {
            $task->setZone($zones[$definition['zone']] ?? throw new \InvalidArgumentException(\sprintf('« %s » : zone inconnue « %s ».', $definition['titre'], $definition['zone'])));
        }

        return $task;
    }

    /** @param array<string, mixed> $definition */
    private function category(array $definition): TaskCategory
    {
        $category = mb_strtolower((string) ($definition['categorie'] ?? 'menage'));

        return self::CATEGORIES[$category] ?? throw new \InvalidArgumentException(\sprintf('Catégorie inconnue : %s', $category));
    }

    /** @return list<array<string, mixed>> */
    private function list(string $section): array
    {
        return array_values(array_filter((array) ($this->data[$section] ?? []), 'is_array'));
    }
}
