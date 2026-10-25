<?php

namespace App\Ledger;

use App\Entity\ShoppingItem;
use App\Enum\ExpenseCategory;

/**
 * The category of an expense whose form left it out, guessed from its title: "Courses Lidl" went on groceries,
 * "Facture EDF" on energy, "Pizzas du vendredi" on going out… "Autre" when nothing fits.
 */
final class ExpenseArt
{
    /**
     * The first category whose words appear in the title wins: the more specific ones come first. The words are
     * matched without accents nor plurals (ShoppingItem::keyOf(): "Netflix" is read "netfli").
     */
    private const WORDS = [
        'pets' => '\bcroquette|\bpatee|\blitiere|\bveto|\bveterinaire|\bfriandise',
        'rent' => '\bloyer|\blocation(?! (de |d.une? )?(voiture|velo|ski))|\bchalet|\bappart|\bcharge\b|\bcaution|\bproprio|\bassurance habitation|\btaxe d.habitation|\bsyndic|\blogement|\bairbnb|\bhotel|\bgite|\bcamping|\bauberge',
        'energy' => '\bedf\b|\bengie|\belectricite|\bgaz\b|\beau\b|\bveolia|\bchauffage|\bfioul|\benergie|\btotalenergie',
        'internet' => '\binternet|\bbox\b|\bfibre|\bfree\b|\bsfr\b|\bbouygue|\borange\b|\bsosh|\bnetfli|\bspotify|\bdeezer|\bdisney|\bcanal|\babonnement|\bprime video|\bforfait (mobile|telephone)',
        'restaurant' => '\brestau|\bfondue|\braclette|\btartiflette|\bresto\b|\bbar\b|\bpizza|\bkebab|\bsushi|\bburger|\bmcdo|\bmac ?do\b|\bapero|\bcafe\b|\bbrasserie|\bdeliveroo|\buber ?eat|\bjust ?eat|\btaco|\bcrepe|\bbiere|\bverre\b',
        'groceries' => '\bcourse|\blidl|\bcarrefour|\bleclerc|\bauchan|\bintermarche|\bmonopri|\bfranpri|\baldi\b|\bcasino|\bsuper ?u\b|\bsupermarche|\bepicerie|\bmarche\b|\bbiocoop|\bpicard|\bboulangerie|\bpain\b|\bboucherie|\bprimeur|\bfromage|\bfruit|\blegume|\bdrive\b|\bnourriture|\bbouffe',
        'house' => '\bikea|\bcastorama|\bleroy|\bbrico|\bmeuble|\baspirateur|\bproduit|\bmenage|\beponge|\blessive|\bpapier toilette|\bpq\b|\bampoule|\bvaisselle|\bdeco\b|\bplante|\boutil|\bpeinture|\bmatela|\bcanape|\bfrigo|\bmachine a laver|\blave.(linge|vaisselle)',
        'transport' => '\bessence|\bcarburant|\bgasoil|\bdiesel|\bpeage|\btrain\b|\bsncf|\btgv\b|\bouigo|\bblablacar|\buber\b|\btaxi|\bparking|\bbus\b|\bmetro|\bnavigo|\bavion|\bvol\b|\bvoiture|\bvelo\b',
        'health' => '\bpharmacie|\bmedecin|\bdocteur|\bmedicament|\bmutuelle|\bdentiste|\bsante\b',
        'gifts' => '\bcadeau|\banniversaire|\banniv\b|\bfleur|\bpot de depart',
        'leisure' => '\bcinema|\bcine\b|\bconcert|\bbowling|\bmusee|\bspectacle|\bbillet|\bplace\b|\bjeu\b|\bescape|\bski\b|\bpiscine|\bsport|\bsoiree|\bfete\b|\bvacance|\bweek.end|\bactivite|\bsortie',
    ];

    public static function guess(string $title): ExpenseCategory
    {
        $key = ShoppingItem::keyOf($title);
        foreach (self::WORDS as $category => $words) {
            if (1 === preg_match('/'.$words.'/u', $key)) {
                return ExpenseCategory::from($category);
            }
        }

        return ExpenseCategory::Other;
    }
}
