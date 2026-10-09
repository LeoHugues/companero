<?php

namespace App\Household;

use App\Entity\CharterRule;
use App\Entity\Household;

/**
 * The charter a new household starts with: common sense for living together, nothing to do with
 * the app. Every coloc then rewrites it as it likes (Réglages de la coloc › La charte).
 */
final class Charter
{
    /** @var list<array{string, ?string}> the kitchen's: the sink is everyone's, so is what is left in it */
    public const KITCHEN_RULES = [
        [
            'Avant de faire la vaisselle, je vide l’égouttoir : jamais de vaisselle mouillée sur de la vaisselle sèche.',
            'Posée sur la pile, la vaisselle mouillée trempe celle qui avait séché : plus rien ne sèche, tout finit par sentir le renfermé, et il faut tout relaver.',
        ],
        [
            'Quand j’ai fini avec l’éponge, je la rince et je l’essore.',
            'Une éponge pleine de restes et d’eau sale devient vite un nid à microbes, et elle sent. Rincée et essorée, elle sèche, sent bon et dure plus longtemps.',
        ],
        [
            'L’évier n’est pas une poubelle : les fins d’assiette vont à la poubelle (ou au compost).',
            'Les restes bouchent le siphon et sentent mauvais. Et si ça arrive quand même, je nettoie derrière moi.',
        ],
    ];

    /** @var list<array{string, ?string}> the rule, and why it matters */
    public const DEFAULT_RULES = [
        [
            'Je fais ma vaisselle quand j’ai fini, pas « plus tard ».',
            'Une assiette qui attend en appelle une autre, et l’évier se remplit tout seul. Rincée tout de suite, elle se lave en une minute ; le lendemain, il faut la faire tremper.',
        ],
        ...self::KITCHEN_RULES,
        [
            'Avant d’aller dormir, je fais un tour : chaussures, pulls, table. Rien ne traîne de mon côté.',
            'Deux minutes le soir, et chacun se lève dans une maison rangée.',
        ],
        [
            'On fait pipi assis.',
            'Debout, on est environ cinq fois plus loin de la cuvette : le jet se fragmente en gouttelettes et éclabousse bien plus loin qu’on ne le croit, sol et murs compris. La physique l’a montré (Splash Lab de l’université Brigham Young, T. Truscott et R. Hurd, « Urinal Dynamics », American Physical Society, 2013). Côté santé, aucune différence chez les hommes en bonne santé ; assis, la vessie se vide mieux en cas de troubles de la prostate (Y. de Jong et al., PLOS ONE, 2014). C’est donc une affaire de propreté : celle de tout le monde, et de qui nettoie les toilettes.',
        ],
        [
            'Ce que je finis, je le remplace : papier toilette, sel, éponge… ou je le signale.',
            null,
        ],
        [
            'Mes affaires ne s’installent pas dans les pièces communes.',
            'Le salon et la cuisine sont à tout le monde : un sac ou un carton peut y passer, pas y vivre.',
        ],
        [
            'Si je ne peux pas faire ce que j’avais pris, je préviens, ou je passe en « Pas là ».',
            'Les rappels vont alors à ceux qui sont à la maison, et personne n’attend pour rien.',
        ],
    ];

    public static function seed(Household $household): void
    {
        foreach (self::DEFAULT_RULES as [$text, $why]) {
            new CharterRule($household, $text, $why);
        }
    }
}
