<?php

namespace App\Controller;

use App\Entity\Gift;
use App\Entity\Member;
use App\Progress\LevelCalculator;
use App\Task\CompletionResult;

/**
 * What was just earned, for the next page to celebrate (templates/_celebration.html.twig):
 * the points, a surprise found in the card, the gifts of a new level.
 */
trait CelebratesCompletions
{
    private function celebrate(CompletionResult $result, Member $member): void
    {
        $bounty = $result->bounty;
        $this->addFlash('completion', [
            'points' => $result->totalPoints(),
            'base' => $result->basePoints,
            'boost' => $result->boostPoints,
            'title' => $result->completion->getTask()->getTitle(),
            'xp' => $result->extraXp(),
            'bounty' => null === $bounty ? null : [
                'kind' => $bounty->getKind()->value,
                'label' => $bounty->label(),
                'icon' => $bounty->getKind()->icon(),
                'description' => $bounty->getKind()->gift()?->description(),
                'gift' => null !== $bounty->getKind()->gift(),
            ],
        ]);
        if ([] !== $result->gifts) {
            $level = $member->getGiftedLevel();
            $this->addFlash('gifts', [
                'level' => $level,
                'rank' => LevelCalculator::rank($level),
                'labels' => array_map(static fn (Gift $gift): string => $gift->getKind()->label(), $result->gifts),
                'items' => array_map(static fn (Gift $gift): array => [
                    'label' => $gift->getKind()->label(),
                    'icon' => $gift->getKind()->icon(),
                    'description' => $gift->getKind()->description(),
                ], $result->gifts),
            ]);
        }
    }
}
