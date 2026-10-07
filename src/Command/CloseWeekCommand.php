<?php

namespace App\Command;

use App\Calendar\Week;
use App\Repository\HouseholdRepository;
use App\Review\WeekCloser;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Meant to run every Monday shortly after midnight (cron), for the week that just ended. */
#[AsCommand(name: 'app:week:close', description: 'Hands out the titles and the team bonus of a finished week')]
final readonly class CloseWeekCommand
{
    public function __construct(
        private HouseholdRepository $households,
        private WeekCloser $closer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Any day of the week to close (defaults to last week)')] ?string $day = null,
    ): int {
        $week = null !== $day ? Week::containing(new \DateTimeImmutable($day)) : Week::containing($this->clock->now())->previous();

        foreach ($this->households->findAll() as $household) {
            $this->closer->close($household, $week);
        }

        $io->success(\sprintf('Semaine %d close (du %s).', $week->number(), $week->start->format('d/m/Y')));

        return 0;
    }
}
