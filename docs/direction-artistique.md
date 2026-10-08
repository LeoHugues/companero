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
- Titres : 18 à 20 px en 800. Corps : 14 à 17 px en 600 et 700. Méta : 12 à
  13 px.

## Formes

- Cartes : rayon de 20 px, bordure de 1,5 px, pas d'ombre au repos. Au survol,
  elles se soulèvent de 2 px avec une ombre douce.
- Boutons principaux : **pilules** terre cuite de 44 px de haut, texte encre en 800.
- Actions secondaires : lien souligné (« Je m'en occupe »).
- La bulle de la Casa est le seul clin d'œil BD : bordure encre et ombre décalée
  de 3 px.
- **Couleurs plates uniquement** : ni dégradé, ni brillance, ni néon.
  Seules exceptions : la **rareté des cartes de tâche**, comme dans un jeu de cartes, et
  l'éclat qui passe sur une jauge pendant qu'elle monte.
- Le **carton jaune** est un vrai carton d'arbitre : jaune franc `#F7D21E`, coins arrondis,
  légèrement penché, ombre encre décalée.

## Cartes de tâche

Une carte à jouer, douce et chaleureuse :

- à gauche, le **médaillon** : l'icône de la catégorie (balai, clé, feuille, caddie, patte…) sur
  un fond pastel propre à la catégorie, entouré d'un **anneau** qui se vide avec le temps qui reste
  (il se remplit à l'arrivée sur la page) ;
- à droite : la rareté (sauf commune), la pastille d'urgence, le petit cadeau d'une surprise, le
  titre en gras, la pièce et le rythme, qui s'en charge ; la roue crantée, discrète, en haut à droite ;
- en bas : **« Je prends »** — une place vide, cerclée de pointillés, avec une main ; prise, elle
  montre le visage de qui s'en occupe — et **« C'est fait »**, un jeton jaune Casa à bord encre et
  ombre décalée (comme la bulle), avec une coche et ce que la tâche rapporte (« +20 ») dans une
  pastille encre. Au toucher, il s'écrase, passe au vert sauge et lance des étoiles.

Plus de bande colorée sur le bord : c'est le médaillon qui dit l'urgence. Toucher la carte ouvre
sa page : la carte en grand, quand elle passe au miel et au corail, qui s'en charge, les dernières
fois qu'elle a été faite. En liste (une pièce), la carte est compacte : un bouton rond jaune.

### Urgence

Des tons de jardin, jamais de rouge d'alarme : la Casa ne gronde pas.

| État | Couleur | Quand | Médaillon |
|---|---|---|---|
| Tout va bien | sauge `#6E9A4C` | avant l'alerte | anneau plein, petite étoile quand c'est tout frais |
| Attention | miel `#E9A53A` | le moment approche, ou il est venu (dans le délai maximum) | le moment venu, il sautille de temps en temps |
| En retard | corail `#E06C55` | au-delà du délai maximum | il gigote, avec un petit « ! » ; le bord de la carte rosit |

Les deux délais se règlent pour chaque tâche (« Orange combien de temps avant ? », « Rouge
combien de temps après ? »). Sans réglage : orange aux 60 % du rythme ou 2 jours avant
l'échéance, rouge 1 jour après.

### Rareté

Choisie pour chaque tâche dans son formulaire, **commune** par défaut :

| Rareté | Carte |
|---|---|
| Commune | Plate, comme les autres cartes |
| Rare | Bordure bleue `#3D7FA8`, léger voile bleu en haut |
| Épique | Bordure en dégradé violet `#8E55BF` → rose → bleu, reflet holographique qui balaie la carte toutes les 6 s |
| Légendaire | Bordure dorée `#E3A21A` où une lumière fait le tour (4,5 s), halo doré, reflet |

Une pastille en capitales (« RARE », « ÉPIQUE »…) avec un petit diamant rappelle la rareté.
Une carte qui cache une **surprise** cette semaine passe d'une rareté au-dessus et porte une
pastille encre avec un petit cadeau qui gigote de temps en temps.
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
| Queue, oreille | Les chats | 3,6 s, 7 s |
| Plumeau, bouffées de poussière | Doigt qui glisse sur la Casa | tant que le doigt bouge |
| Montée des jauges | À l'arrivée sur la page : depuis la dernière valeur vue (avec le gain, « +4 % ») ou depuis zéro | 0,9 à 1,3 s |
| Compteurs | Points, XP, propreté : les chiffres défilent jusqu'à leur valeur | 0,9 s |
| « C'est fait » | Le bouton s'écrase, passe au vert, lance des étoiles | 500 ms |
| Points gagnés | Une pastille encre descend du haut avec les points qui défilent | 3,4 s |
| Boîte cadeau | Surprise et nouveau niveau : la boîte tremble, s'ouvre au toucher, confettis | 380 ms + 2,2 s |
| Carton jaune reçu | Le carton arrive en tournoyant | 900 ms |

| Friandise | Les croquettes tombent dans la gamelle, le chat mâche, des cœurs, le téléphone ronronne | 2,6 s |

Après « C'est fait » (ou tout formulaire qui revient sur la même page), la page se met à jour
sur place, sans recharger : la célébration, les jauges et la Casa jouent tout de suite, y
compris dans l'appli Android.

L'accueil a un raccourci vers **le plan de la maison** : un petit plan de quatre pièces colorées
dans une pastille à bord encre, à côté de la propreté.

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

## Ton

On tutoie, on reste chaleureux, on fait un peu d'humour : « C'est fait »,
« Je m'en occupe », « Prendre soin de la Casa ». Pas de classement, pas de
reproche. Les titres de niveau sont drôles (« Apprenti balai », « Chevalier de
la serpillière »).
