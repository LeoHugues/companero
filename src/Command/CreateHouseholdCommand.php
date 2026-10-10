<?php

namespace App\Command;

use App\Entity\CatalogItem;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Household\Blueprint;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Repository\HouseholdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Starts a real household from a description file (config/coloc/notre-coloc.yaml), with its first
 * member; the others join with the invitation link.
 */
#[AsCommand(name: 'app:coloc:creer', description: 'Crée une coloc à partir d’un fichier de description, avec son premier membre')]
final readonly class CreateHouseholdCommand
{
    public function __construct(
        private HouseholdFounder $founder,
        private HouseholdRepository $households,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument('Le fichier qui décrit la coloc')] string $file,
        #[Option('Prénom du premier membre')] ?string $nom = null,
        #[Option('Son pseudo, pour se connecter')] ?string $pseudo = null,
    ): int {
        try {
            $blueprint = Blueprint::fromFile($file);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return 1;
        }

        if ([] !== $this->households->findAll() && !$io->confirm('Une coloc existe déjà sur ce serveur. En créer une autre ?', false)) {
            $io->warning('Rien n’a été créé.');

            return 1;
        }

        $founding = new Founding();
        $founding->householdName = $blueprint->name();
        $founding->cleaningDay = $blueprint->cleaningDay();
        $founding->name = $nom ?? (string) $io->ask('Ton prénom');
        $founding->username = $pseudo ?? (string) $io->ask('Ton pseudo, pour te connecter');
        // Hidden only when typed in a terminal: on Windows, a hidden answer is read from the keyboard, never from a pipe.
        $founding->plainPassword = (string) $io->askQuestion((new Question('Ton mot de passe (8 caractères au moins)'))->setHidden($this->isTyped($input)));
        if (!$this->isValid($io, $founding)) {
            return 1;
        }

        try {
            [$member, $created] = $this->entityManager->wrapInTransaction(function () use ($blueprint, $founding): array {
                $member = $this->founder->found($founding, withDefaults: false);
                $created = $blueprint->applyTo($member->getHousehold(), $member, $this->clock->now());
                foreach ($created as $entity) {
                    if (!$this->isValid(null, $entity)) {
                        throw new \InvalidArgumentException(\sprintf('Élément invalide dans le fichier : %s', $this->describe($entity)));
                    }
                    $this->entityManager->persist($entity);
                }

                return [$member, $created];
            });
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return 1;
        }

        $household = $member->getHousehold();
        $io->success(\sprintf(
            '« %s » est prête : %d zones, %d animal(aux), %d tâches, %d modèles au catalogue.',
            $household->getName(),
            $this->count($created, Zone::class),
            $this->count($created, Pet::class),
            $this->count($created, Task::class),
            $this->count($created, CatalogItem::class),
        ));
        $io->text(['Lien d’invitation à envoyer aux autres colocs :', $this->urls->generate('onboarding_join', ['token' => $household->getInviteToken()], UrlGeneratorInterface::ABSOLUTE_URL)]);

        return 0;
    }

    private function isTyped(InputInterface $input): bool
    {
        $stream = ($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? \STDIN;

        return stream_isatty($stream);
    }

    /**
     * @param list<object> $entities
     * @param class-string $class
     */
    private function count(array $entities, string $class): int
    {
        return \count(array_filter($entities, static fn (object $entity): bool => $entity instanceof $class));
    }

    private function isValid(?SymfonyStyle $io, object $value): bool
    {
        $violations = $this->validator->validate($value);
        foreach ($violations as $violation) {
            $io?->error(\sprintf('%s : %s', $violation->getPropertyPath(), $violation->getMessage()));
        }

        return 0 === \count($violations);
    }

    private function describe(object $entity): string
    {
        return method_exists($entity, 'getTitle') ? $entity->getTitle() : (method_exists($entity, 'getName') ? $entity->getName() : $entity::class);
    }
}
