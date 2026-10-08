<?php

namespace App\Controller;

use App\Entity\Gift;
use App\Entity\Member;
use App\Task\CompletionResult;

/** The Casa thanks the member on the next page, and any gift of a new level shows up. */
trait CelebratesCompletions
{
    private function celebrate(CompletionResult $result, Member $member): void
    {
        $this->addFlash('completion', ['points' => $result->totalPoints(), 'title' => $result->completion->getTask()->getTitle(), 'xp' => $result->boostXp]);
        if ([] !== $result->gifts) {
            $this->addFlash('gifts', [
                'level' => $member->getGiftedLevel(),
                'labels' => array_map(static fn (Gift $gift): string => $gift->getKind()->label(), $result->gifts),
            ]);
        }
    }
}
