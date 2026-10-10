<?php

namespace App\Task;

use App\Entity\Task;

/**
 * The little picture in the arch of a task card (templates/components/TaskVignette.html.twig),
 * guessed from the task's title: a vacuum cleaner, the toilet, the bin… Null when nothing fits:
 * the card then shows its category.
 */
final class TaskArt
{
    /** The first picture whose words appear in the title wins: the more specific ones come first. */
    private const PICTURES = [
        'vacuum' => '\baspir',
        'mop' => '\bserpilli|\blaver le sol|\bsols?\b',
        'plumbing' => '\bchasse\b|\bfuite|\brobinet|\bplomb|\brepar|\bbricol',
        'toilet' => '\btoilettes?\b|\bwc\b',
        'dump' => '\bdechett?eri|\bencombrants?\b',
        'bin' => '\bpoubelle|\bordures?\b|\bdechets?\b|\bverre\b|\brecycl|\bcompost',
        'window' => '\bvitres?\b|\bfenetres?\b|\bmiroirs?\b|\bcarreaux\b',
        'terrace' => '\bterrasse|\bbalcon|\bbalayer\b|\bfeuilles\b',
        'cutlery' => '\bcouverts?\b|\btiroirs?\b|\bcouteaux\b|\bfourchettes?\b|\bcuilleres?\b',
        'dishwasher' => '\blave-?vaisselle|\blave vaisselle',
        'fridge' => '\bfrigo|\brefrigerateur|\bcongel',
        'washer' => '\blave-?linge|\blave linge|\blinge\b|\blessive|\bmachine a laver|\bseche-?linge|\bdraps?\b',
        'counter' => '\bplans? de travail|\bcuisine\b|\bvaisselle|\begouttoir|\bfour\b|\bplaques?\b',
        'cupboard' => '\bplacards?\b|\barmoires?\b|\bpenderie|\betageres?\b|\bdressing',
        'parking' => '\bparking|\bgarage|\ballee\b|\bportail|\bcour\b',
        'mess' => '\bvomi|\bpipi\b|\bcrottes?\b',
        'cobweb' => '\baraign|\bplumeau|\bdepoussier|\bpoussieres?\b',
        'mower' => '\btond|\btonte\b|\bgazon|\bpelouse|\bherbes?\b',
        'sofa' => '\bsalon\b|\bcanape|\bcoussins?\b|\bsejour\b',
    ];

    private const ACCENTS = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'"];

    public static function of(Task $task): ?string
    {
        $title = strtr(mb_strtolower($task->getTitle()), self::ACCENTS);
        foreach (self::PICTURES as $picture => $words) {
            if (1 === preg_match('/'.$words.'/u', $title)) {
                return $picture;
            }
        }

        return null;
    }
}
