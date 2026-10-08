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
  Seule exception : la **rareté des cartes de tâche**, comme dans un jeu de cartes.

## Rareté des cartes de tâche

Plus une tâche rapporte de points (de base, sans les boosts), plus sa carte est rare. Les
petites tâches, les plus nombreuses, sont communes.

| Rareté | Points | Carte |
|---|---|---|
| Commune | moins de 20 | Plate, comme les autres cartes |
| Rare | 20 à 29 | Bordure bleue `#3D7FA8`, léger voile bleu en haut |
| Épique | 30 à 49 | Bordure en dégradé violet `#8E55BF` → rose → bleu, reflet holographique qui balaie la carte toutes les 6 s |
| Légendaire | 50 et plus | Bordure dorée `#E3A21A` où une lumière fait le tour (4,5 s), halo doré, reflet |

Une pastille en capitales (« RARE », « ÉPIQUE »…) avec un petit diamant rappelle la rareté
au-dessus du titre. Les animations s'arrêtent si le téléphone demande moins d'animations.

## La Casa

| Humeur | Propreté | Visage | Bulle (exemple) |
|---|---|---|---|
| Poussiéreuse | < 80 % | bouche en moue, poussière autour | « Je me sens un peu poussiéreuse… » |
| Ça va | 80–91 % | petit sourire | « Ah, ça respire déjà mieux ! » |
| Rayonnante | ≥ 92 % | grand sourire, joues roses, scintillements | « Je brille ! » |

- **La poussière** diminue au fur et à mesure que la propreté monte.
- **Quand une tâche est faite**, la Casa fait un saut, ferme les yeux de joie,
  envoie des cœurs et remercie le membre dans sa bulle (« Merci Léo ! +4 pts »).
- **Au repos**, elle respire légèrement.
- La Casa parle à la première personne, au féminin, en tutoyant. Elle est
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

Toutes les animations sont coupées avec `prefers-reduced-motion`.

## Ton

On tutoie, on reste chaleureux, on fait un peu d'humour : « C'est fait »,
« Je m'en occupe », « Prendre soin de la Casa ». Pas de classement, pas de
reproche. Les titres de niveau sont drôles (« Apprenti balai », « Chevalier de
la serpillière »).
