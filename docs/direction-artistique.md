# Direction artistique — « Compagnon »

> Piste retenue : **F · Compagnon**. Les maquettes sont sur le canevas de design
> du projet (page « Compagnon »).

## Concept

La maison est un personnage : **la Casa**, la *compañera* de la coloc. Son
humeur reflète l'état du foyer. Elle est poussiéreuse et boudeuse quand on
la néglige, elle rayonne quand elle est propre. Chaque tâche faite la fait
réagir.

C'est la traduction visuelle du principe n°1 de la vision : **motiver, pas
contrôler**. On prend soin de la Casa *ensemble* plutôt que de se comparer.
L'objectif collectif est mis en avant, et l'objectif personnel reste
discret.

## Palette

| Rôle | Couleur | Usage |
|---|---|---|
| Fond | `#FFF6E9` | Fond d'écran (crème) |
| Surface | `#FFFCF6` | Cartes, barre de navigation |
| Bordure | `#F0E2CC` | Contours de cartes (1,5 px) |
| Piste | `#F0DFC6` | Fond des barres de progression, ombre de la Casa |
| Encre | `#2E1E14` | Texte principal, traits de la Casa |
| Texte secondaire | `#7A6555` | Méta-infos (5,1:1 sur le fond) |
| **Terre cuite** | `#E8692C` | Couleur d'action : boutons, toit de la Casa, progression. **Texte encre dessus**, jamais blanc. |
| Terre cuite foncée | `#B4521F` | Texte d'accent (points, retard) : 4,7:1 sur le fond |
| Jaune | `#F6C453` | Corps de la Casa, pastilles « jour de ménage » |
| Brun | `#8C5A3C` | Couleur d'un membre |
| Sable | `#D9B48C` | Couleur d'un membre |
| Poussière | `#B9A58C` | Poussière autour de la Casa, états « frais » |
| Joues | `#F2A27A` | Rougissement de la Casa |
| Sauge, miel, corail | `#6E9A4C`, `#E9A53A`, `#E06C55` | Urgence des tâches (avec leurs fonds pâles) |

Chaque membre a une couleur (terre cuite, jaune, brun, sable) utilisée dans
les barres collectives.

## Typographie

- **Bricolage Grotesque** (Google Fonts), graisses de 400 à 800, et rien
  d'autre.
- Titres de page : 26 px en 700, serrés (`tracking-tight`), équilibrés sur leurs lignes ; titres de
  section : 19 à 21 px en 700. Corps : 14 à 15 px en 500 et 600. Méta : 12,5 à 13 px.
- Les étiquettes (au-dessus d'un groupe, d'une jauge) en **petites capitales** : 11 px en 600, espacées.
- Le 800 est réservé aux moments de jeu : le niveau qui pop, les initiales des visages.

## Formes

- **Feuilles** : rayon de 22 px, surface, pas de bordure, une ombre douce qui les soulève. Les
  lignes d'une liste sont séparées par un **filet** (`line` à 70 %), jamais par des cadres.
- Bouton principal : terre cuite, 48 px de haut (52 px en bas d'un formulaire), rayon de 14 px,
  texte encre en 700, un léger biseau (comme « Ajouter » dans la barre du bas). Rarement plus d'un par écran.
- Actions secondaires : une petite feuille (« Plan », « Modifier ») ou du sable dans une feuille
  (« Copier le lien », « Ajouter la zone ») ; un lien gris pour ce qui s'éloigne (« Se déconnecter »).
- Choix : des puces à filet, l'encre une fois choisies (comme « Là » sur l'accueil) ; pour un choix
  parmi peu, le contrôle segmenté de l'accueil (sable, curseur crème).
- La bulle de la Casa est le seul clin d'œil BD : un trait encre, une queue, une ombre douce.
- **Couleurs plates uniquement** : ni dégradé, ni brillance, ni néon.
  Seules exceptions : la **rareté des cartes de tâche**, comme dans un jeu de cartes, et
  l'éclat qui passe sur une jauge pendant qu'elle monte.
- Le **carton jaune** est un vrai carton d'arbitre : jaune franc `#F7D21E`, coins arrondis,
  légèrement penché, ombre encre décalée.

## Cartes de tâche

Une carte à jouer, douce et chaleureuse :

- dans son **coin**, comme l'index d'une carte : ce qu'elle rapporte (« 20 ») et le dessin de sa catégorie
  (balai, clé, feuille, caddie, patte…) ;
- à côté : un point de couleur et son état en petites capitales (« AUJOURD'HUI », « EN RETARD DE 2 J »),
  les points de l'engagement de la semaine (un par fois attendue, remplis en sauge une fois faits), le
  titre, puis la pièce et le rythme sur **une seule ligne courte** (« Salon +2 · tous les 2 j ») et un
  **fil** qui se vide avec le temps qui reste ;
- à droite, **sa vignette** dans une arche : un dessin deviné du titre (aspirateur, toilettes, poubelle,
  vitres…), sinon le dessin de sa catégorie sur son fond pastel ;
- sur toute la largeur : la note de cette fois (dans un cartouche pâle) et qui s'en charge ;
- en bas, sous un filet : **« Je prends »** — une place vide, cerclée de pointillés terre cuite, dans une
  pastille rosée ; prise, elle montre le visage de qui s'en occupe — et **« C'est fait »**, un jeton jaune
  Casa avec une coche et ce que la tâche rapporte (« +20 ») après un filet. Au toucher, il s'écrase, passe
  au vert sauge et lance des étoiles ; la carte se retourne et montre son dos terre cuite, qui dit merci.

Toucher la carte ouvre **sa page** : la même carte en grand (son titre est celui de la page), puis ce
qu'elle vaut cette fois, une note pour cette fois, ce qu'on en sait, les dernières fois qu'elle a été
faite. En liste (une pièce), la carte est compacte : sans vignette, un bouton rond jaune.

### Urgence

Des tons de jardin, jamais de rouge d'alarme : la Casa ne gronde pas.

| État | Couleur | Quand | Sur la carte |
|---|---|---|---|
| Tout va bien | sauge `#6E9A4C` | avant l'alerte | le point et le fil en sauge, le fil presque plein |
| Attention | miel `#E9A53A` | le moment approche, ou il est venu (dans le délai maximum) | le point et le fil en miel |
| En retard | corail `#E06C55` | au-delà du délai maximum | le point corail pulse, le fil est vide |

Les deux délais se règlent pour chaque tâche (« Orange combien de temps avant ? », « Rouge
combien de temps après ? »). Sans réglage : orange aux 60 % du rythme ou 2 jours avant
l'échéance, rouge 1 jour après.

### Rareté

Choisie pour chaque tâche dans son formulaire, **commune** par défaut. Elle ne s'écrit jamais sur la
carte : c'est **la finition de son bord**, et plus elle est rare, plus elle brille, sans jamais crier.

| Rareté | Bord | Lumière |
|---|---|---|
| Commune | papier `#E9DFCF` | aucune |
| Rare | argent bleuté, l'index bleu | un reflet passe toutes les 9 s |
| Épique | nacre (lilas, rose, menthe, vanille), l'index violet | toutes les 7 s, un halo violet |
| Légendaire | or, la face dorée et quelques étoiles qui scintillent | toutes les 5 s, un halo doré |

Dans le formulaire, chaque rareté se choisit avec une mini-carte de sa finition ; dans la liste des
modèles, elle n'est qu'un petit diamant de sa couleur ; sur la page d'un modèle, une mini-carte.

Quand la Casa **nomme une carte** dans sa bulle (la tâche qu'elle demande), son titre se lit comme une
carte liée dans le chat d'un jeu : entre crochets, en gras, à la couleur de sa rareté (encre pour une
commune, bleu, violet, or), après une mini-carte de sa finition (papier, argent, nacre, or). À
l'apparition, la mini-carte est distribuée et les lettres du titre sont battues — des enseignes ♠ ♥ ♦ ♣
et quelques signes passent dessus — avant de se poser une à une, de gauche à droite, chacune d'un petit
éclat terre cuite ; puis la lumière passe sur le titre d'une rare, et repasse de temps en temps sur une
épique ou une légendaire. La bulle ne change jamais de taille pendant ce temps.
Une carte qui cache une **surprise** cette semaine passe d'une rareté au-dessus et est
**emballée comme un cadeau** : un ruban terre cuite barre son coin en haut à droite (deux surpiqûres
crème, au-dessus de la pointe de la vignette), noué d'un petit nœud qui gigote de temps en temps.
Sur la page de la tâche, la même surprise se dit dans un encart terre cuite pâle, avec un cadeau qui gigote.
Les animations s'arrêtent si le téléphone demande moins d'animations.

## La Casa

Une scène (un seul SVG) : la maison au milieu — cheminée qui fume, lucarne, toit de tuiles —,
quelques touffes d'herbe, et **les chats de la coloc** de part et d'autre.

| Humeur | Propreté | Visage | Autour | Bulle (exemple) |
|---|---|---|---|---|
| Négligée | < 55 % | paupières lourdes tombant vers l'extérieur, bouche triste | toile d'araignée et son araignée, pansement sur le mur, murs ternes, pas de fumée, herbe sèche | « Pfiou… j'ai des toiles d'araignée partout. » |
| Poussiéreuse | 55–79 % | bouche en moue | poussière autour, éternuements | « Je me sens un peu poussiéreuse… » |
| Ça va | 80–91 % | petit sourire, joues légèrement roses | fleurs blanches et roses | « Ah, ça respire déjà mieux ! » |
| Rayonnante | ≥ 92 % | grand sourire, langue, joues roses | scintillements, fleurs | « Je brille ! » |

- **La nuit** (23 h – 7 h), elle dort : yeux fermés, « Zzz », lucarne allumée, les chats dorment aussi.

### La météo

Sur l'accueil, le ciel du paysage suit l'humeur de la Casa (`App\Enum\CasaWeather`) : plus la maison
est propre, plus il fait beau. Des tons crème qui grisent, jamais de bleu froid ni de noir.

| Humeur | Météo | Le ciel |
|---|---|---|
| Rayonnante | Grand soleil | soleil dont les rayons tournent, halo qui pulse, deux oiseaux qui planent, un seul petit nuage |
| Ça va | Beau temps | soleil, deux nuages qui dérivent |
| Poussiéreuse | Ciel couvert | ciel grisé, soleil pâle sans rayons derrière un gros nuage, quatre nuages ombrés |
| Négligée | Pluie | nuages gris, la pluie tombe devant la Casa et les chats (jamais sur la bulle), éclaboussures, flaques qui ondulent |
| Négligée, < 30 % | Orage | nuages sombres, pluie plus drue, un éclair jaune et le ciel qui s'illumine toutes les 9 s |

- **La nuit**, la lune remplace le soleil (cachée quand il pleut) ; par beau temps, des étoiles scintillent.
- **Quand une tâche est faite**, la pluie s'arrête et un arc-en-ciel se dessine au-dessus d'elle (sauge,
  miel, corail, bleu, jaune : les couleurs de l'appli) ; quand elle se calme, il s'efface et le temps
  revient à celui de la propreté.
- Touchée, elle parle aussi du ciel (« Il pleut sur mon toit… Une tâche et ça se lève, promis. »).
- **La poussière** diminue au fur et à mesure que la propreté monte.
- **Au repos**, elle respire, cligne des yeux, regarde à droite et à gauche (avec une souris,
  elle suit le pointeur) ; poussiéreuse, elle éternue de temps en temps.
- **Au toucher**, elle rit, envoie des cœurs et répond une phrase au hasard selon son humeur.
- **Le plumeau** : un doigt qui glisse sur elle fait apparaître un plumeau qui suit le doigt et
  soulève la poussière. Elle glousse (« Hihi, ça chatouille ! »), la poussière s'en va ; assez
  frottée, elle scintille et remercie. C'est cosmétique : la vraie propreté vient des tâches,
  la poussière revient au bout de 30 s.
- **Quand une tâche est faite**, la Casa fait un saut, ferme les yeux de joie,
  envoie des cœurs et remercie le membre dans sa bulle (« Merci Léo ! +4 pts »).
- **Au repos**, elle respire légèrement.
- La Casa parle à la première personne, au féminin, en tutoyant.

### Les chats

Les chats déclarés dans *La coloc* (deux au plus) sont dessinés d'après leur description :
la robe (« roux », « brun », « tigré », « noir », « gris », « blanc », « crème »), la taille
(« petit », « mince » ; « gros », « maine coon »), le poil (« poils longs »). Un petit chat
s'assoit à gauche, queue enroulée ; un gros se couche à droite comme un pain, la queue devant.
Chez nous : **Tishka**, petite rousse à poils longs (collerette, joues touffues), et le **gros
chat** brun foncé tigré, plus clair sur le ventre.

Ils clignent des yeux, battent de la queue, bougent une oreille ; touchés, ils sautillent et
miaulent (« Miaou ! », « Prrrr… »). Quand une tâche est faite, ils sautent avec la Casa. Elle est
  bienveillante, avec un humour léger, et ne culpabilise jamais.
- Idée : chaque foyer peut renommer sa maison.

## Animations

| Effet | Où | Durée |
|---|---|---|
| Respiration | La Casa au repos | 3,2 s en boucle |
| Saut | La Casa quand une tâche est faite | 700 ms |
| Cœurs qui s'envolent | Quand une tâche est faite | 1,2 s |
| Scintillement | La Casa rayonnante | 1,8 s en boucle |
| Apparition élastique | Bulle, bandeau de niveau | 360 ms |
| Remplissage | Barres de progression | 600 ms |
| Pression | Boutons (réduction à 0,95) | 120 ms |
| Clignement, regard | La Casa et les chats | toutes les 5 s environ |
| Fumée | Cheminée (sauf négligée) | 3,3 s en boucle |
| Soleil | Rayons qui tournent (beau temps, grand soleil), halo qui pulse | 40 s / 24 s, 3,6 s en boucle |
| Nuages, oiseaux | Le ciel de l'accueil | 34 à 69 s, 9 s en boucle |
| Pluie | Gouttes, éclaboussures, ronds dans les flaques (négligée) | 0,6 à 1,3 s en boucle |
| Éclair | Orage (< 30 %) | toutes les 9 s |
| Arc-en-ciel | Quand une tâche est faite | 1,1 s, s'efface en 1,6 s |
| Queue, oreille | Les chats | 3,6 s, 7 s |
| Plumeau, bouffées de poussière | Doigt qui glisse sur la Casa | tant que le doigt bouge |
| Montée des jauges | À l'arrivée sur la page : depuis la dernière valeur vue (avec le gain, « +4 % ») ou depuis zéro | 0,9 à 1,3 s |
| Compteurs | Points, XP, propreté : les chiffres défilent jusqu'à leur valeur | 0,9 s |
| « C'est fait » | Le bouton s'écrase, passe au vert, lance des étoiles | 500 ms |
| Piocher, « Je prends », lâcher sa carte | Au toucher, sans attendre le serveur : le siège change d'un petit rebond, la carte entre dans la main (qui glisse jusqu'à elle et s'écarte pour elle) ou en sort (elle s'efface, ses voisines se resserrent) ; une quête prise laisse place à la suivante, la dernière se replie | 0,4 à 1,2 s |
| Carte nommée par la Casa | La mini-carte distribuée, les lettres battues qui se posent une à une, la lumière sur le titre | 520 ms, 0,7 s, puis 2,4 à 6 s |
| Points gagnés | Une pastille encre descend du haut avec les points qui défilent | 3,4 s |
| Boîte cadeau | Surprise et nouveau niveau : la boîte tremble, s'ouvre au toucher, confettis | 380 ms + 2,2 s |
| Carton jaune reçu | Le carton arrive en tournoyant | 900 ms |

| Friandise | Les croquettes tombent dans la gamelle, le chat mâche, des cœurs, le téléphone ronronne | 2,6 s |
| Tirer pour rafraîchir | Une petite Casa descend du ciel dans sa bulle, curieuse (« oh »), pendant qu'un anneau terre cuite se remplit ; pleine, elle sourit. Lâchée, elle sautille pendant que la page se met à jour sur place, puis ferme les yeux de joie (anneau sauge) et remonte | tant que le doigt tire, puis 750 ms au moins + 650 ms |

Le rafraîchissement du navigateur (et celui de l'appli Android) est remplacé par celui de la Casa :
on garde la main sur l'animation, et la page se met à jour sur place, comme après « C'est fait ».
Il ne se déclenche pas si un champ a été modifié, pour ne jamais perdre une saisie.

Après « C'est fait » (ou tout formulaire qui revient sur la même page), la page se met à jour
sur place, sans recharger : la célébration, les jauges et la Casa jouent tout de suite, y
compris dans l'appli Android.

Piocher, « Je prends » et lâcher sa carte n'attendent pas le serveur : tout se joue au toucher, et la
page revenue ne s'applique qu'une fois le mouvement fini, sans rien couper en route. Ce que le serveur
a décidé autrement (une carte prise par quelqu'un d'autre juste avant) arrive ou s'en va de la même
façon ; sans réseau, tout revient comme avant. La mise à jour sur place laisse de côté ce qui n'a pas
changé (la plupart des cartes, les dessins) : elle ne fige plus le téléphone.

L'accueil a un raccourci vers **le plan de la maison** : une pastille « Plan » posée sur le ciel
de la Casa.

## L'état de la maison

Sous la Casa, **une seule feuille à deux faces**. Pour passer de l'une à l'autre, une pilule
**incrustée** dans le bord de la feuille, à moitié sur l'herbe, centrée sous la Casa : le sable des
filtres de l'accueil, cerclé de la surface de la feuille, sans ombre. Un curseur crème glisse sous la
face ouverte, dont le dessin prend sa couleur : la maison en terre cuite, la coupe en miel.

- **Propreté**, par défaut : l'humeur de la Casa, son pourcentage, la jauge et ses paliers, ce qui
  manque pour la prochaine humeur, puis la tâche qu'elle demande (« Je prends », « Une autre ? ») ;
- **Objectif** : les points de la coloc sur l'objectif de la maison, l'échéance (J-1…), la jauge et
  les jours, les visages de la coloc, la série, et le tiroir de ce qui a rapporté ces points.

Un glissement du doigt sur la feuille la tourne aussi (vers la gauche, l'objectif). Les deux faces se
croisent : celle qui part glisse et s'efface, celle qui arrive entre du côté où va le curseur, et ses
jauges et compteurs repartent de zéro (420 ms). La feuille a la hauteur de la plus grande face, tiroir
replié : la tourner ne déplace rien en dessous ; quand le tiroir est ouvert, sa hauteur glisse. La face
choisie tient quand la page se met à jour sur place (après « C'est fait »), et la propreté revient à
chaque nouvelle visite de l'accueil.

## La barre du bas

Fine (58 px), sur la surface, avec un filet et une ombre douce au-dessus. Quatre dessins
légendés (Accueil, Tâches, Bilan, Profil) et, au milieu, **« Ajouter »** : un bouton rond terre
cuite de 54 px, plus encre, qui sort de la barre posé sur une **bosse** de la même surface (le seul
relief de la barre). La page ouverte se remplit des couleurs de la Casa et rebondit :

| Onglet | Dessin | Ouvert |
|---|---|---|
| Accueil | une petite maison avec un visage | elle devient la Casa : corps jaune, toit terre cuite |
| Tâches | deux cartes à jouer en éventail | la carte du fond rosit, celle de devant porte un carreau terre cuite |
| Bilan | trois barres | jaune, terre cuite, jaune |
| Profil | le visage du membre (sa couleur, son initiale) | cerclé d'encre |

## Ce qui manque

Ce qu'il faut racheter, dans le calme de l'accueil :

- **Sur l'accueil**, sous l'état de la maison : une feuille fine. À gauche, le caddie sur une tuile
  lilas (la couleur des courses), une pastille corail dans son coin s'il y a de l'urgent ; « IL MANQUE »
  en petites capitales et le nombre ; dessous, les noms à la suite (« Papier toilette · Éponges ·… »),
  l'urgent d'abord, en corail, sur une seule ligne. À droite, un **+** dans le sable d'une feuille.
  Vide : « Rien pour l'instant ».
- **La page** : en tête, une feuille pour le dire — le champ « Il manque… », la puce « Urgent » (à
  filet, l'encre une fois choisie) et « Ajouter », le seul bouton terre cuite ; sous un filet,
  « SOUVENT » et ses puces, chacune un **+** terre cuite foncée et un nom.
- **La liste** : « URGENT » avec son point corail, puis « À ACHETER ». Une ligne par chose : son
  dessin sur sa tuile pastel, deviné de son nom (`App\Shopping\ItemArt`) — un rouleau (lilas) pour le
  papier, une éponge ou un flacon (jaune ménage) pour l'entretien, un sac (sable), une ampoule, une
  pile ou une clé (bleu bricolage), une tasse (sable) pour le café, un panier (sauge) pour ce qui se
  mange, une patte (rose) pour les animaux, le caddie sinon ; le nom, qui l'a noté et quand
  (« Robin · jeudi ») ; au bout, une **case ronde** cerclée de sable.
- **Cochée**, sans attendre le serveur, la case se remplit de sauge d'un petit rebond et lance
  quelques étoiles, le nom se barre, la ligne pâlit ; la liste revient sans elle.
- **Acheté ces derniers jours** : un volet replié ou non, une coche sauge sur sa tuile, qui l'a acheté
  et quand, « Remettre » dans le sable.
- **Vide**, la Casa : « Il ne manque rien : mes placards sont pleins ! Le premier qui voit le fond d'un
  flacon le note ici. »

## Les comptes

L'argent, sans alarme : **sauge** quand on vous doit, **terre cuite** quand vous devez, jamais de rouge.

- **Sur l'accueil**, sous « Il manque », la même feuille fine : des pièces sur une tuile sauge, « LES
  COMPTES » en petites capitales, dessous où l'on en est (« On te doit 11,57 € » en sauge, « Tu dois… »
  en terre cuite, compte par compte s'il y en a plusieurs ; « Partager une dépense » sans compte), un
  **+** dans le sable.
- **La liste des comptes** : une ligne par compte, la maison sur une tuile sauge pour celui de la
  coloc, des pièces sur une tuile lilas pour les autres ; les visages de ceux qui sont dedans, un peu
  chevauchés ; ce qui a été dépensé ; mon solde à droite. Sans compte, la Casa explique, et une feuille
  propose « Ouvrir les comptes de la coloc », le seul bouton terre cuite.
- **Un compte** : en tête, une feuille « MON SOLDE » (« On te doit », le montant en grand, sauge ou
  terre cuite), puis « Mes dépenses » et « Total des dépenses » ; sous elle, le contrôle segmenté de
  l'accueil, **Dépenses** et **Équilibres**, qui basculent sur place. « Ajouter une dépense » **flotte**
  au-dessus de la barre du bas.
- **Les dépenses** : une recherche dans une petite feuille ; les jours en petites capitales
  (« AUJOURD'HUI », « JEUDI 22 OCTOBRE ») ; une ligne par dépense : le dessin de sa catégorie sur sa
  tuile pastel (un panier sauge pour les courses, une clé sable pour le logement, un éclair jaune pour
  l'énergie, le wifi bleu, une éponge jaune pour la maison, une fourchette rose pour les sorties, une
  voiture bleue, un ticket lilas, un cœur rose, une patte, un cadeau ; deux flèches sauge pour un
  remboursement), le titre, « Payé par Léo » (ou « Gab → Léo »), le montant et « ta part » dessous.
- **Les équilibres** : chaque visage, son solde, et une **barre qui part du milieu** — vers la droite en
  sauge quand on lui doit, vers la gauche en terre cuite quand il doit — ; « A payé… · sa part… ».
  Puis « POUR ÊTRE QUITTES » : les deux visages et une flèche, la phrase (« Léa doit 32,88 € à Robin »,
  « Tu dois… » en terre cuite), et **« Régler »** dans le sable : touché, sans attendre le serveur, il
  se remplit de sauge d'un petit rebond et lance quelques étoiles, la ligne pâlit. Tout le monde quitte,
  la Casa : « pas un centime ne traîne ! »
- **Le formulaire** : Dépense, Revenu, Transfert dans le contrôle segmenté ; le montant en grand, la
  devise à côté (et son taux quand ce n'est pas celle du compte) ; « PAYÉ PAR » avec les visages ;
  « POUR QUI » : une case ronde qui se remplit de sauge, le visage et le nom, les parts (− et +) ou un
  montant, la part de chacun en terre cuite foncée ; dessous, ce qu'il reste à répartir (terre cuite)
  ou « Le compte est bon » (sauge). Les catégories avec leur dessin.

## Le plan
La maison vue d'en haut, dans le calme de l'accueil, sur une feuille :

- **le jardin** tout autour, vert prairie (celui du paysage de la Casa), quelques touffes d'herbe et
  des buissons fleuris ; il sèche quand le jardin est négligé ;
- **des murs clairs** (sable `#D3BFA3`), plus de gros traits noirs, et l'ombre de la maison sur l'herbe ;
- **les sols** : crème quand tout va bien, miel pâle puis corail pâle selon l'humeur de la pièce
  (mêmes seuils que la Casa : 55, 80, 92 %), avec leur matière devinée du nom de la pièce — parquet,
  carrelage là où il y a de l'eau, lames de bois pour une terrasse ;
- **la poussière** posée dans les pièces qui ont besoin d'un coup de main, **une étincelle** dans celles
  à 100 % ;
- le nom, puis une pastille de couleur (sauge, miel, corail) et le pourcentage ; ce qui presse dans
  une pastille terre cuite qui sautille.

Dessous, **pièce par pièce**, de celle qui a le plus besoin de toi à la plus propre : la petite Casa
de son humeur, une jauge sauge, miel ou corail, ce qu'il y a à faire.

### Vibrations

Chaque retour se sent dans la main, chacun avec son toucher (`buzz()` dans `assets/lib/fx.js`).
Dans l'appli Android, ce sont les vraies vibrations du téléphone (clics, montées, coups sourds,
avec leur intensité : `HapticsComponent.kt`) ; dans un navigateur, le même rythme.

| Effet | Quand |
|---|---|
| tick | chaque appui : bouton, puce, lien, onglet, volet |
| toggle | un interrupteur (Là / Pas là) |
| press | « C'est fait », une tâche express |
| success | les points gagnés, un boost activé |
| rise | une jauge qui monte après un gain |
| reward | une boîte surprise qui s'ouvre |
| levelup | un nouveau niveau |
| card | un carton jaune sifflé ou reçu |
| alert | « Ça arrive ! » |
| giggle, dust, sparkle, sneeze | la Casa chatouillée, le grain du plumeau, la Casa qui brille, qui éternue |
| purr | un chat caressé, une friandise |

Un élément choisit son effet avec `data-haptic="…"` (ou `none`). Rien ne vibre si le téléphone
demande moins d'animations.

Toutes les animations sont coupées avec `prefers-reduced-motion`.

## Les autres pages

Toutes les pages parlent comme l'accueil : le calme des feuilles, des filets et des petites capitales.

- **L'en-tête** : un retour (ou une croix pour un formulaire) dans une petite feuille carrée, et ce qui
  mène ailleurs dans une petite feuille avec son dessin (« Le modèle », « Réglages », « Plan ») ; au-dessus
  du titre, en petites capitales, d'où l'on vient (« La coloc », « Semaine 43 · du 19 au 25 octobre »).
- **Les listes** sont des feuilles, une ligne par chose, un filet entre deux : une vignette, un visage ou
  un dessin sur une tuile pastel à gauche, le titre en 600 et sa méta, ce que ça rapporte en terre cuite,
  un chevron sable à droite. Les modèles et le catalogue montrent la vignette de leur carte ; les chats,
  leur portrait (celui de la Casa).
- **Ce qu'on sait** d'une tâche (« En bref », « La règle ») : le mot à gauche, sa valeur à droite.
- **Les formulaires** sont rangés en groupes (« La tâche », « Quand », « Alertes de la carte », « Où, et
  qui », « Sa carte »), une feuille par groupe sous son étiquette. Les champs sont crème sur la feuille,
  cerclés de terre cuite au focus. Les colocs se choisissent avec leur visage, les catégories avec leur
  dessin, la rareté avec une mini-carte de sa finition. Le bouton principal **flotte** au-dessus de la barre
  du bas (et de la bosse d'« Ajouter ») et la page s'efface en crème derrière lui ; au bout du formulaire,
  il reprend sa place.
- **Les curseurs** : une piste sable remplie de terre cuite jusqu'au pouce, terre cuite cerclé de crème.
- **La page d'une tâche** montre la vraie carte en grand ; **la découverte** fait essayer une vraie carte.
- **La connexion** et **l'arrivée dans une coloc** reprennent le haut de l'accueil : la Casa en grand dans
  son paysage, qui parle, le nom de l'appli sur son ciel, et le formulaire dans une feuille posée sur son
  herbe.
- **Le bilan** : les barres de la semaine montent à l'arrivée (jaune, terre cuite le jour de ménage) ; les
  titres de la semaine attendent face cachée, comme la pioche de l'accueil, et se retournent au toucher.
- **Les célébrations** (surprise, niveau, carton) gardent leur boîte qui tremble et leurs confettis, sur
  une feuille douce plutôt qu'un cadre encre.
- **Vide** : un cadre en pointillés fins, une phrase de la Casa.

## Ton

On tutoie, on reste chaleureux, on fait un peu d'humour : « C'est fait »,
« Je m'en occupe », « Prendre soin de la Casa ». Pas de classement, pas de
reproche. Les titres de niveau sont drôles (« Apprenti balai », « Chevalier de
la serpillière »).
