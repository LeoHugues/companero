<?php

namespace App\Review;

use App\Entity\Completion;
use App\Enum\Urgency;
use App\Progress\MemberProgress;

/** Picks the funny title of the week for a member — there is always a kind one to give. */
final class TitleAwarder
{
    /** @param list<Completion> $completions the member's completions during the week */
    public function award(MemberProgress $progress, array $completions, int $cleaningDay): AwardedTitle
    {
        $count = \count($completions);
        $rescues = \count(array_filter($completions, static fn (Completion $c): bool => Urgency::Late === $c->getUrgency()));
        $onCleaningDay = \count(array_filter(
            $completions,
            static fn (Completion $c): bool => Urgency::Due === $c->getUrgency() && (int) $c->getCompletedAt()->format('N') === $cleaningDay,
        ));
        [$favourite, $favouriteCount] = $this->favouriteTask($completions);

        return match (true) {
            !$progress->wasPresent() => new AwardedTitle('En vadrouille', 'absent toute la semaine'),
            $progress->goal > 0 && $progress->points >= 1.5 * $progress->goal => new AwardedTitle('Tornade ménagère', \sprintf('%d pts pour un objectif de %d', $progress->points, $progress->goal)),
            $rescues > 0 => new AwardedTitle('As du rattrapage', \sprintf('%d %s sauvée%s du retard', $rescues, $rescues > 1 ? 'tâches' : 'tâche', $rescues > 1 ? 's' : '')),
            $onCleaningDay >= 2 => new AwardedTitle('Pilier du jour de ménage', \sprintf('%d tâches pile le bon jour', $onCleaningDay)),
            $favouriteCount >= 3 => new AwardedTitle('Spécialiste '.$favourite, \sprintf('%d fois cette semaine', $favouriteCount)),
            $progress->reached() => new AwardedTitle('Fidèle au poste', 'objectif atteint'),
            $count > 0 => new AwardedTitle('Petit pas, grand cœur', \sprintf('%d %s cette semaine', $count, $count > 1 ? 'tâches' : 'tâche')),
            default => new AwardedTitle('En mode économie d’énergie', 'la semaine prochaine sera la bonne'),
        };
    }

    /**
     * @param list<Completion> $completions
     *
     * @return array{0: string, 1: int}
     */
    private function favouriteTask(array $completions): array
    {
        $counts = array_count_values(array_map(static fn (Completion $c): string => $c->getTask()->getTitle(), $completions));
        if ([] === $counts) {
            return ['', 0];
        }
        arsort($counts);

        return [(string) array_key_first($counts), (int) reset($counts)];
    }
}
