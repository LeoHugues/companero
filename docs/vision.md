# Companero — Vision produit

> Document vivant. Il capture l'état de la réflexion avant toute ligne de code.

## 1. Intention

Une application pour organiser l'intendance d'une colocation : ménage, bricolage,
jardin, courses ponctuelles… Le constat de départ : les colocataires ne manquent
pas de bonne volonté, mais ils ne voient pas le temps passer. Si on n'agit pas un
peu chaque jour ou chaque semaine, tout s'accumule.

L'application doit :

- **rendre visible** ce qui doit être fait et depuis quand ça n'a pas été fait ;
- **aider à se fixer des objectifs**, comme une appli d'apprentissage de langue ;
- **motiver** par une couche ludique (points, niveaux, titres).

Elle est d'abord construite pour une coloc de 4 personnes. Elle pourra ensuite
être partagée en open source, chaque coloc hébergeant sa propre instance.

## 2. Principes

1. **Motiver, pas contrôler.** Ni classement, ni culpabilité. L'objectif n'est
   pas que tout le monde fasse exactement la même chose, mais que chacun en
   fasse un peu plus.
2. **Deux appuis maximum** pour dire « j'ai fait ça ». Si c'est pénible,
   personne ne l'utilisera.
3. **Confiance par défaut.** Une tâche déclarée faite n'est pas validée par
   les autres.
4. **Gouvernance légère.** Tout le monde peut créer et modifier les tâches et
   ajuster les points.
5. **Glissant par nature, engagé par choix.** Une tâche revient quand elle en a
   besoin. On peut en plus s'engager à la faire un minimum de fois par semaine.

## 3. Glossaire

| Terme | Définition |
|---|---|
| **Foyer** | La colocation. Une instance = un foyer (au moins en V1). |
| **Membre** | Un colocataire. |
| **Zone** | Un espace du foyer (cuisine, salon, salle de bain du haut, chambre de X, jardin…) avec la liste des membres qu'il **concerne**. Une zone est *commune* (tout le monde), *partagée* (plusieurs membres) ou *privée* (un seul membre). |
| **Tâche** | Quelque chose à faire, rattaché à une zone. Elle est **récurrente** (permanente, elle revient) ou **ponctuelle** (elle disparaît une fois faite). |
| **Catalogue** | Des modèles de tâches ponctuelles prêts à l'emploi (tailler la haie, nettoyer les gouttières, faire une course…), instanciés à la main quand le besoin se présente. |
| **Rythme** | Pour une tâche récurrente glissante : l'intervalle idéal entre deux réalisations (« environ tous les 3 jours »). |
| **Engagement** | Optionnel : le nombre minimum de réalisations par semaine sur lequel le foyer s'engage (« au moins 1 fois par semaine »). |
| **Échéance** | Date ou heure limite d'une tâche : récurrente calendaire (« poubelles le mardi soir ») ou ponctuelle (« appeler le proprio avant vendredi »). |
| **Urgence** | L'état calculé d'une tâche : *fraîche*, *à prévoir*, *due*, *en retard*. |
| **Réservation** | « Je vais le faire » : un membre s'attribue une tâche pour une durée limitée. Passé ce délai, la tâche est de nouveau libre. |
| **Réalisation** | L'événement « membre M a fait la tâche T à telle date », avec sa valeur en points, figée au moment de la réalisation. |
| **Points** | Valeur d'une réalisation. Ce n'est pas du temps passé, mais un mélange de temps, de pénibilité et de corvée. |
| **Objectif hebdo** | Le nombre de points qu'un membre vise sur la semaine, au prorata de sa présence. |
| **Absence** | Une période où un membre n'est pas là. Elle réduit son objectif et met sa série en pause. |
| **Bilan hebdo** | Le récapitulatif de fin de semaine : engagements tenus, objectifs atteints, titres gagnés. |
| **Jauge de la maison** | Un indicateur collectif de l'état du foyer (« la maison est propre à 72 % »). |

## 4. Les tâches

### 4.1 Trois façons de revenir

| Type | Exemple | Comportement |
|---|---|---|
| **Glissante** | Aspirateur salon, rythme 3 j | L'urgence monte depuis la dernière réalisation. Chaque réalisation remet le compteur à zéro. |
| **Glissante + engagement** | Serpillière salon, rythme 7 j, engagement 1/sem | Comme la glissante, mais si l'engagement de la semaine n'est pas tenu, l'urgence monte aussi à l'approche de dimanche. |
| **Calendaire** | Sortir les poubelles, mardi 20 h | Elle réapparaît à date fixe et a une échéance précise. |

**Concilier le glissant et l'hebdomadaire.** Une tâche avec engagement a deux
sources d'urgence, et c'est **la plus forte des deux** qui s'affiche :

- l'urgence de **rythme** : le temps écoulé depuis la dernière réalisation,
  rapporté au rythme ;
- l'urgence d'**engagement** : le nombre de réalisations qui manquent cette
  semaine, rapporté aux jours qui restent avant dimanche.

Exemple : serpillière, rythme 7 j, engagement 1/sem. Elle a été faite le jeudi
de la semaine passée. Lundi, l'urgence de rythme est faible (4 j sur 7), mais
l'engagement de la semaine n'est pas encore tenu. Si elle n'est toujours pas
faite samedi, elle passe en « due » même si le rythme seul ne l'exigeait pas
encore. La semaine reste le cadre qui fixe l'objectif ; le glissant dicte le
quotidien.

### 4.2 États d'urgence (proposition)

```
fraîche ──► à prévoir ──► due ──► en retard
 (< 60 %)    (60–100 %)   (100 % + marge)   (au-delà de la marge, ex. 24 h)
```

Les pourcentages sont exprimés par rapport au rythme ou à l'échéance. La
**marge** (24 h par défaut) se règle tâche par tâche.

### 4.3 Tâches ponctuelles et catalogue

- N'importe quel membre peut créer une tâche ponctuelle en quelques secondes :
  un titre, et éventuellement une zone, des points et une échéance.
- Le **catalogue** contient les tâches rares ou saisonnières (tailler la haie,
  tondre, gouttières…). Elles ne se planifient pas seules : on les instancie
  quand on voit le besoin.
- Les **courses** sont une catégorie de tâches ponctuelles (« acheter du
  papier toilette »). La gestion des stocks et des consommables est hors
  périmètre.

### 4.4 Cycle de vie

- **Libre** → **Réservée** (« je vais le faire », avec un délai) → **Faite**.
- On peut passer directement de *Libre* à *Faite*, **même si la tâche n'est pas
  encore due** (elle remet alors le compteur à zéro).
- Une réservation expirée rend la tâche libre de nouveau.
- Une tâche récurrente n'est **jamais dupliquée** : elle reste une seule carte,
  de plus en plus urgente.

## 5. Les points

- **Valeur de base** définie sur la tâche. Pour amorcer : 5 min ≈ 1 point, puis
  ajustement collectif selon la pénibilité.
- **Bonus de retard** : une tâche en retard prend de la valeur (par exemple
  +1 point par jour de retard, plafonné). Les corvées impopulaires s'équilibrent
  d'elles-mêmes.
- **Ajustement ponctuel** : sur une réalisation, n'importe qui peut dire « cette
  fois c'était plus, ou moins » (±).
- Les points sont stockés comme un **journal d'événements** (réalisations,
  ajustements, et plus tard services ou cartons). Les totaux sont calculés à
  partir de ce journal.

### Périmètre des points

Toutes les réalisations comptent pour l'objectif personnel, quelle que soit la
zone : la salle de bain privée compte autant que la salle de bain partagée.
« Séparer sans trop séparer » :

- **objectif personnel et XP** : toutes les zones ;
- **jauge de la maison** : uniquement les zones communes et partagées ;
- **chambres** : comptent, mais la tâche peut être désactivée par chaque membre
  pour sa propre chambre.

## 6. Membres, absences, objectifs

- Chaque membre a un **objectif hebdo** en points (il le choisit lui-même, ou
  on part d'une valeur commune).
- L'objectif est **proratisé** selon la présence : 3 jours d'absence sur 7, cela
  donne 4/7 de l'objectif.
- Une **série** compte les semaines consécutives où l'objectif est atteint.
  Elle est gelée pendant les absences, jamais cassée.

## 7. Gamification

- **XP et niveaux** : les points cumulés font monter de niveau.
- **Titres** humoristiques, attribués au bilan (« Seigneur de la serpillière »,
  « Le retour du Jedi » pour une tâche très en retard rattrapée…).
- **Pas de classement.** Le bilan met en avant ce que chacun a fait, sans
  ordonner les membres.
- **Jauge de la maison** : une moyenne de « fraîcheur » des tâches récurrentes
  des zones communes, affichée en permanence.

### Cartons (à garder en mémoire, pas en V1)

Un système de pénalités légères et drôles : chaque membre dispose par exemple de
**3 cartons par semaine** à distribuer pour des broutilles (vaisselle qui
traîne…). Le carton doit être motivé par une phrase. L'effet est symbolique :
un titre ou un gage, pas une vraie perte de points. Garde-fous à définir pour
que ça reste un jeu.

## 8. Bilan hebdomadaire

Il est généré le dimanche soir et consultable le lundi :

- engagements tenus et non tenus ;
- objectifs personnels atteints, séries en cours ;
- évolution de la jauge de la maison ;
- titres de la semaine.

## 9. Périmètre

| V1 (MVP à tester dans la coloc) | Plus tard |
|---|---|
| Foyer, membres, zones | Plusieurs foyers par instance |
| Tâches récurrentes (glissante, engagement, calendaire) | Cartons |
| Tâches ponctuelles + catalogue | Services entre membres / mini-économie |
| Réserver / faire / ajuster les points | Classes de personnage |
| Urgence + bonus de retard | Vue Kanban |
| Objectif hebdo + absences | Statistiques avancées |
| Bilan hebdo, XP, niveaux, quelques titres | |
| Jauge de la maison | |
| Notifications (rappels) | |

## 10. Choix techniques (provisoires)

- **Backend** : Symfony (domaine d'expertise), base relationnelle, serveur
  auto-hébergé. Livraison sous forme d'image Docker pour l'open source.
- **Client** : une PWA servie par Symfony (Turbo/Stimulus ou Live Components),
  installable sur Android. Habillage natif possible ensuite via l'intégration
  Hotwire Native de Symfony UX (à étudier au moment du setup).
- **Pas de tâche planifiée pour générer les tâches** : l'urgence est *calculée*
  à partir des réalisations et des rythmes. Seuls le bilan hebdo et les
  notifications ont besoin d'une tâche planifiée.
- **Journal d'événements** pour les réalisations et les points. Ça garde la
  porte ouverte au local-first et à un éventuel cœur en Rust plus tard.

## 11. Questions ouvertes

1. Formule exacte du bonus de retard (linéaire ? plafond ?).
2. Une tâche faite à plusieurs : points partagés ou doublés ?
3. Durée par défaut d'une réservation : 24 h ? Réglable par tâche ?
4. Objectif hebdo : choisi par chacun, ou valeur commune ?
5. Notifications : lesquelles, à quelle fréquence, et comment ne pas devenir
   agaçant ?
6. Début et fin de semaine : du lundi 00:00 au dimanche 23:59 ? Heure du bilan ?
7. Formule de la jauge de la maison.
