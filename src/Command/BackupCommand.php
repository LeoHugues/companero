<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** A consistent copy of the SQLite database, even while the app is in use (deploys, nightly cron). */
#[AsCommand(name: 'app:backup', description: 'Saves a copy of the SQLite database in var/backups/')]
final readonly class BackupCommand
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/var/backups')] private string $directory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Number of backups to keep, the oldest ones are deleted')] int $keep = 30,
    ): int {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $io->error('Seule une base SQLite se sauvegarde ainsi : utilisez l’outil de votre base (pg_dump…).');

            return 1;
        }
        $database = $this->connection->getParams()['path'] ?? null;
        if (null === $database || !is_file($database)) {
            $io->note('Pas encore de base à sauvegarder.');

            return 0;
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o770, true)) {
            $io->error(\sprintf('Impossible de créer %s.', $this->directory));

            return 1;
        }
        $target = \sprintf('%s/%s-%s.db', $this->directory, pathinfo($database, \PATHINFO_FILENAME), $this->clock->now()->format('Ymd-His'));
        // VACUUM INTO writes a consistent snapshot, unlike copying a file that may be written to.
        $this->connection->executeStatement('VACUUM INTO '.$this->connection->quote($target));

        $backups = glob($this->directory.'/*.db') ?: [];
        rsort($backups);
        foreach (\array_slice($backups, max(1, $keep)) as $old) {
            unlink($old);
        }

        $io->success(\sprintf('Base sauvegardée dans %s.', $target));

        return 0;
    }
}
