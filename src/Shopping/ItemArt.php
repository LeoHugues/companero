<?php

namespace App\Shopping;

use App\Entity\ShoppingItem;

/**
 * The drawing of something missing, on its pastel tile, guessed from its name: a roll for the toilet paper, a
 * sponge, a bottle for the vinegar, a bulb… The shopping cart when nothing fits.
 */
final class ItemArt
{
    /**
     * The first drawing whose words appear in the name wins: the more specific ones come first. The words are
     * matched without accents nor plurals (ShoppingItem::keyOf()).
     */
    private const DRAWINGS = [
        'paw' => ['tile-blush', '\bcroquette|\bpatee|\blitiere|\bfriandise'],
        'bulb' => ['tile-sky', '\bampoule|\blampe|\bneon|\bspot\b'],
        'battery' => ['tile-sky', '\bpile\b|\bbatterie|\bchargeur'],
        'wrench' => ['tile-sky', '\bvis\b|\bclou\b|\bscotch|\bjoint\b|\bfusible|\bcolle\b|\bpeinture|\bcheville'],
        'bag' => ['tile-sand', '\bsac\b|\bsachet'],
        'roll' => ['tile-lilac', '\bpapier|\bpq\b|\bessuie|\bsopalin|\bmouchoir|\bcoton|\balu(minium)?\b|\bfilm\b'],
        'sponge' => ['tile-sun', '\beponge|\blingette|\bchiffon|\bmicrofibre|\bserpilliere|\bgant\b|\bbrosse'],
        'bottle' => ['tile-sun', '\bvinaigre|\bjavel|\bliquide|\blessive|\badoucissant|\bsavon|\bgel\b|\bshampo|\bdentifrice|\bdeodorant|\bnettoyant|\bproduit|\bdetartrant|\bdesinfectant|\bspray|\bbicarbonate|\bsel regenerant|\bpastille|\bhuile'],
        'cup' => ['tile-sand', '\bcafe|\bthe\b|\btisane|\bcapsule|\bdosette|\bfiltre'],
        'basket' => ['tile-sage', '\bpate\b|\briz\b|\bfarine|\bsucre|\blait\b|\bbeurre|\boeuf|\bpain\b|\bfromage|\bfruit|\blegume|\bepice|\bconserve|\bcereale|\bconfiture|\bsel\b|\bpoivre|\bmoutarde|\bketchup|\bbiere|\bvin\b|\beau\b|\bjus\b|\bchocolat|\byaourt|\bcreme'],
    ];

    /** @return array{icon: string, tile: string} */
    public static function of(string $name): array
    {
        $key = ShoppingItem::keyOf($name);
        foreach (self::DRAWINGS as $icon => [$tile, $words]) {
            if (1 === preg_match('/'.$words.'/u', $key)) {
                return ['icon' => $icon, 'tile' => $tile];
            }
        }

        return ['icon' => 'cart', 'tile' => 'tile-lilac'];
    }
}
