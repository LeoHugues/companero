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
| **Modèle** | La règle d'une tâche qui revient : régulière (« tous les 7 jours »), à jour fixe (« le mardi soir ») ou express. Les modèles sont dans l'onglet *Modèles* ; ils ne se font pas, ils produisent des tâches à faire. |
| **Tâche à faire** | Ce qui attend sur l'accueil, avec sa carte (rareté, urgence, bouton « C'est fait ») : l'occurrence du moment d'un modèle, ou une tâche **ponctuelle** (faite une fois, puis elle disparaît). |
| **Catalogue** | Des modèles de tâches ponctuelles prêts à l'emploi (tailler la haie, nettoyer les gouttières, faire une course…), lancés à la main quand le besoin se présente. |
| **Rythme** | Pour une tâche récurrente glissante : l'intervalle idéal entre deux réalisations (« environ tous les 3 jours »). |
| **Engagement** | Optionnel : le nombre minimum de réalisations par semaine sur lequel le foyer s'engage (« au moins 1 fois par semaine »). |
| **Jour de ménage** | Un jour de la semaine choisi par le foyer (par exemple le samedi). C'est le rendez-vous où l'on sait que c'est le moment de faire le ménage. Il n'est ni obligatoire ni exclusif : on peut faire les tâches avant ou après. |
| **Échéance** | Date ou heure limite d'une tâche : récurrente calendaire (« poubelles le mardi soir ») ou ponctuelle (« appeler le proprio avant vendredi »). |
| **Urgence** | L'état calculé d'une tâche : *fraîche*, *à prévoir*, *due*, *en retard*. |
| **Réservation** | « Je vais le faire » : un membre s'attribue une tâche pour une durée limitée. Passé ce délai, la tâche est de nouveau libre. |
| **Réalisation** | L'événement « membre M a fait la tâche T à telle date », avec sa valeur en points, figée au moment de la réalisation. |
| **Points** | Valeur d'une réalisation. Ce n'est pas du temps passé, mais un mélange de temps, de pénibilité et de corvée. |
| **Objectif hebdo** | Le nombre de points qu'un membre vise sur la semaine, au prorata de sa présence. |
| **Présence** | Le nombre de jours par semaine où un membre est là (0 à 7). Elle proratise son objectif ; à 0, sa série est en pause. |
| **À la maison** | Interrupteur « là / pas là » du moment : les rappels vont aux membres présents. |
| **Tâche express** | Une petite tâche jamais planifiée, signalée en un appui quand elle est faite (« j'ai vidé le lave-vaisselle »). |
| **Animal** | Un animal de la coloc. Ses tâches (repas, litière…) ont une personne qui s'en charge et un remplaçant. |
| **Boost** | Pendant 24 h, +1 point tous les 3 points. Automatique le jour de ménage, ou gagné en montant de niveau. |
| **Cadeau** | Ce qu'apporte un nouveau niveau (ou une surprise) : boost coloc, boost ciblé, boost d'XP, gel de série, friandise, carton jaune. |
| **Surprise** | Une petite récompense cachée au hasard dans une carte pour la semaine. La carte passe d'une rareté au-dessus ; la première personne qui fait la tâche la trouve. |
| **Carton jaune** | Se gagne (niveaux, surprises) et se donne à un coloc, pour un truc en particulier. Symbolique : aucun point perdu. |
| **Bilan hebdo** | Le récapitulatif de fin de semaine : engagements tenus, objectifs atteints, titres gagnés. |
| **Jauge de la maison** | Un indicateur collectif de l'état du foyer (« la maison est propre à 72 % »). |
| **Profil à réclamer** | Un coloc connu par son prénom seulement, préparé par les autres. Il le réclame avec le lien d'invitation (« Je suis Robin ») et y retrouve tout ce qui lui est attaché. |
| **Découverte** | La visite guidée par la Casa à l'arrivée : l'esprit, les cartes et le glissement, les points, les deux objectifs, la charte, puis ses propres réglages. Voir [l'onboarding](onboarding.md). |
| **Charte** | Les règles de bon sens de la coloc (« je fais ma vaisselle quand j'ai fini »), écrites par tout le monde. Chacun y adhère (« Ça me va »), et la relit quand elle change. |

## 4. Les tâches

### 4.1 Trois façons de revenir

| Type | Exemple | Comportement |
|---|---|---|
| **Glissante** | Aspirateur salon, rythme 3 j | L'urgence monte depuis la dernière réalisation. Chaque réalisation remet le compteur à zéro. |
| **Glissante + engagement** | Serpillière salon, rythme 7 j, engagement 1/sem | Comme la glissante, mais si l'engagement de la semaine n'est pas tenu, l'urgence monte aussi à l'approche du jour de ménage, puis de la fin de semaine. |
| **Calendaire** | Sortir les poubelles, mardi 20 h ; nourrir les chats, tous les jours à 19 h | Elle réapparaît à date fixe (un jour de la semaine, ou tous les jours) et a une échéance précise. |
| **Express** | Vider le lave-vaisselle | Souvent et vite fait. Jamais planifiée, jamais urgente : on la signale en un appui depuis l'accueil quand on l'a faite. Un délai minimal (en heures ou en jours) empêche de la refaire juste après. Elle ne compte pas dans la jauge de la maison. |
| **Occasionnelle** | Nettoyer le vomi de Gizmo, réparer la chasse d'eau | Situationnelle : elle dort jusqu'à ce que quelqu'un dise **« Ça arrive ! »** (un appui sur l'accueil, ou dans les modèles). Sa carte apparaît alors, à faire tout de suite ; elle passe en retard après le délai maximum (1 jour par défaut). Une fois faite, elle se rendort. Signalée, elle compte dans la jauge de la maison. |

**Concilier le glissant et l'hebdomadaire.** Une tâche avec engagement a deux
sources d'urgence, et c'est **la plus forte des deux** qui s'affiche :

- l'urgence de **rythme** : le temps écoulé depuis la dernière réalisation,
  rapporté au rythme ;
- l'urgence d'**engagement** : le nombre de réalisations qui manquent cette
  semaine, rapporté au temps qui reste. Le **jour de ménage** sert d'échéance
  douce : la tâche est « due » ce jour-là. Elle ne passe « en retard » qu'à la
  fin de la semaine.

Exemple : la serpillière a un rythme de 7 j et un engagement de 1 fois par
semaine, et le jour de ménage est le samedi. Elle a été faite le jeudi de la
semaine passée. Lundi, l'urgence de rythme est faible (4 j sur 7), mais
l'engagement de la semaine n'est pas encore tenu. Samedi, elle apparaît
« due », même si le rythme seul ne l'exigeait pas encore. Si elle n'est
toujours pas faite dimanche soir, l'engagement n'est pas tenu. La semaine
reste le cadre qui fixe l'objectif, et le glissant dicte le quotidien.

### 4.2 Le jour de ménage

**Un seul** jour par semaine, fixé au niveau du foyer, sert de **rendez-vous**
commun.
Il n'est pas impératif : tout ce qui est fait avant ou après compte normalement.
Ce jour-là :

- **les tâches avec engagement passent « dues »** si elles ne sont pas encore
  faites dans la semaine ;
- **une notification le matin** présente la liste du jour : les tâches dues,
  les plus urgentes et celles qui ne sont pas réservées ;
- **un boost** s'applique automatiquement à tout ce qui est fait ce jour-là
  (désactivable, voir § 5) ;
- *(idée)* un **défi collectif** : si la jauge de la maison dépasse un seuil à
  la fin du jour de ménage, tous les membres présents gagnent un petit bonus.
  C'est un moteur coopératif, qui complète l'objectif personnel.

### 4.3 États d'urgence (proposition)

```
fraîche ──► à prévoir ──► due ──► en retard
 (< 60 %)    (60–100 %)   (100 % + marge)   (au-delà de la marge, ex. 24 h)
```

Les pourcentages sont exprimés par rapport au rythme ou à l'échéance. La
**marge** (24 h par défaut) se règle tâche par tâche.

### 4.4 Tâches ponctuelles et catalogue

- N'importe quel membre peut créer une tâche ponctuelle en quelques secondes :
  un titre, et éventuellement une zone, des points et une échéance.
- Le **catalogue** contient les tâches rares ou saisonnières (tailler la haie,
  tondre, gouttières…). Elles ne se planifient pas seules : on les instancie
  quand on voit le besoin.
- Les **courses** sont une catégorie de tâches ponctuelles (« acheter du
  papier toilette »). La gestion des stocks et des consommables est hors
  périmètre.

### 4.5 Cycle de vie

- **Libre** → **Réservée** (« je vais le faire », avec un délai) → **Faite**.
- On peut passer directement de *Libre* à *Faite*, **même si la tâche n'est pas
  encore due** (elle remet alors le compteur à zéro).
- Une réservation expirée rend la tâche libre de nouveau.
- Une tâche récurrente n'est **jamais dupliquée** : elle reste une seule carte,
  de plus en plus urgente.

## 5. Les points

- **Valeur de base** définie sur la tâche. Pour amorcer : **5 min ≈ 10 points**, puis
  ajustement collectif selon la pénibilité (les toilettes valent plus que leurs 5 minutes).
- **Pas de bonus de retard ni de ponctualité** : une tâche rapporte ses points, que
  ce soit à l'heure ou en retard. Seuls le **jour de ménage** et les **boosts**
  rapportent plus.
- **Noter après coup** : on peut noter une tâche déjà faite (à la création, « C'est
  déjà fait »), et corriger une réalisation (date, heure, qui, points). Les points
  comptent à la date de la réalisation, et les boosts sont ceux de ce moment-là.
- **Ajustement ponctuel** : sur une réalisation, n'importe qui peut dire « cette
  fois c'était plus, ou moins » (±5).
- **Boosts** : +1 point tous les 3 points de base, pendant 24 h. Le jour de ménage,
  un boost s'applique automatiquement à tout le monde (désactivable). Les boosts
  d'un même type ne s'additionnent pas.
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

## 6. Membres, présence, objectifs

- Chaque membre a un **objectif hebdo** en points : 70, 140, 210 ou 280
  (5, 10, 15 ou 20 minutes par jour).
- Plutôt que des dates d'absence, chacun règle dans son profil un **curseur cranté
  de 0 à 7 jours de présence par semaine**. Il vaut pour la semaine en cours et les
  suivantes (les semaines passées gardent le leur). L'objectif est **proratisé** :
  4 jours sur 7, cela donne 4/7 de l'objectif.
- Un interrupteur **« Là / Pas là »** (accueil, profil, et bientôt une tuile des
  réglages rapides Android) dit qui est à la maison en ce moment.
- Une **série** compte les semaines consécutives où l'objectif est atteint.
  Elle est en pause les semaines à 0 jour ; un **gel de série** la sauve une fois.

## 6 bis. Animaux et rappels ciblés

- Les animaux de la coloc se déclarent dans *La coloc*. Leurs tâches sont dans la
  catégorie « Animaux » et peuvent viser un animal en particulier.
- Une tâche peut avoir **une personne qui s'en charge** et **un remplaçant**.
  Le rappel va à la personne si elle est à la maison, sinon au remplaçant s'il est
  là, sinon à **toute la coloc présente**. Sans personne désignée, toute la coloc
  présente est concernée.
- L'accueil montre « On compte sur toi » ; `GET /api/rappels` donne la même liste
  pour les notifications de l'appli (à brancher).

## 7. Gamification

- **XP et niveaux** : les points cumulés font monter de niveau.
- **Titres** humoristiques, attribués au bilan (« Seigneur de la serpillière »,
  « Le retour du Jedi » pour une tâche très en retard rattrapée…).
- **Pas de classement.** Le bilan met en avant ce que chacun a fait, sans
  ordonner les membres.
- **Jauge de la maison** : à quel point les tâches des zones communes sont **tenues à temps**,
  en moyenne, affichée en permanence. Une tâche qui n'est pas encore due compte pleinement (100,
  ou 92 quand son moment approche), quelle que soit sa jauge de fraîcheur : la maison n'est pas sale
  parce que l'aspirateur reviendra dans trois jours. Le moment venu, elle compte 70 ; en retard, 45,
  puis 15 de moins par jour de retard. Une coloc dans les temps est donc rayonnante (≥ 92 %).
  Elle est incarnée par **la Casa**, la maison-personnage dont l'humeur suit la
  jauge (voir [direction artistique](direction-artistique.md)).
- **Objectif de la maison** : un objectif hebdomadaire propre à la coloc (250 pts
  par défaut, réglable), atteint avec les points de tout le monde. La contribution
  de chacun est affichée, avant l'objectif personnel.
  Sur l'accueil, le module se déplie (« Ce qui a rapporté ces points ») : qui a contribué,
  sans classement, puis les tâches de la semaine jour par jour, chacune avec ses points.
- **Deux séries** : la série personnelle (son objectif atteint) et la **série de la
  coloc** (l'objectif de la maison atteint), coopérative.
- **Plan de la maison** : le vrai plan, vu d'en haut, dessiné d'après
  [l'esquisse](esquisse-plan-maison.svg) : chaque pièce a sa forme (des points sur
  une grille, réglables dans les réglages de la zone), le sol fonce quand elle a
  besoin d'attention, le jardin fait le tour. Les zones sans forme s'affichent en
  tuiles. Une pièce montre ses tâches et ce qui y a été fait.

### Cadeaux de niveau

Chaque niveau apporte un cadeau, à tour de rôle : **boost coloc** (tout le monde,
24 h), **gel de série**, **boost ciblé** (pour un autre coloc, jamais pour soi),
**boost d'XP** (de l'XP en plus, qui ne compte pas pour l'objectif). Quand la coloc
a des animaux, chaque niveau apporte aussi une **friandise**, à donner à l'un
d'eux ou à tous : une page à elle montre les croquettes tomber dans la gamelle, le chat qui mâche
et ronronne, puis « Continuer ». Un niveau sur deux (3, 5, 7…) apporte en plus un **carton jaune**.
Un gel de série peut être offert à un coloc. La page *Mes récompenses* (depuis le
profil) montre le chemin des niveaux et ce que chacun apporte.

### Titres de la semaine

Au bilan, chaque membre reçoit un titre drôle (« Pilier du jour de ménage »,
« As du rattrapage », « Tornade ménagère »…), **révélé au toucher** comme une
carte à retourner. Les titres gagnés forment une collection dans le profil ;
des titres secrets viendront plus tard.

### Bonus collectif

Quand la coloc atteint l'objectif de la maison,
chaque membre présent gagne **+50 XP** à la clôture de la semaine. De l'XP, pas des points : les
points mesurent le temps et la pénibilité de ce qui a été fait, on ne les distribue pas. L'XP fait
monter de niveau (et ouvre des cadeaux), sans compter pour l'objectif perso.

### Rappels

Trois rappels, réglables par chacun : le matin du jour de ménage, quand une tâche
traîne (au plus un par jour), et le bilan du dimanche soir. *Les réglages existent ;
l'envoi des notifications (Web Push) reste à faire.*

### Surprises de la semaine

Quand la semaine est planifiée (`app:week:close`, le lundi après minuit, ou à défaut la première
page ouverte de la semaine), quelques cartes tirées au hasard cachent une **surprise** : environ
une carte sur cinq parmi les tâches qui reviennent (ni les express, ni celles de tous les jours),
au moins une, au plus quatre. Tant que personne ne l'a trouvée, la carte passe **d'une rareté
au-dessus** (commune → rare → épique → légendaire) et porte un petit ruban cadeau.

La première personne qui fait la tâche dans la semaine ouvre la boîte : des points en plus
(+10, +15 ou +25, qui comptent pour l'objectif), de l'XP en plus (+30 ou +60, pour l'XP
seulement) ou un cadeau (carton jaune, boost d'XP, boost coloc, gel de série, friandise si la
coloc a des animaux). Noter après coup une tâche déjà faite n'ouvre pas de surprise. La page
*Mes récompenses* montre les surprises de la semaine, trouvées ou non.

### Cartons jaunes

Ceux qui font des choses ont le droit de siffler : un **carton jaune** se gagne un niveau sur deux
(niveaux 3, 5, 7…) ou dans une surprise, et se donne depuis le profil à un coloc, **pour un truc
en particulier** (« la vaisselle qui traîne »). Celui qui le reçoit le voit en grand à sa
prochaine visite. L'effet est symbolique : aucun point perdu.

*Idées de règles, pour plus tard :* deux cartons jaunes dans la même journée font un carton rouge,
sinon un carton s'annule tout seul au bout de 24 h mais reste dans les bilans ; ou bien vu à la
semaine (deux ou trois cartons jaunes dans la semaine font « un truc en plus »). Garde-fous à
définir pour que ça reste un jeu.

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
| Tâches récurrentes (glissante, engagement, calendaire) | Règles des cartons (rouge, expiration) |
| Tâches ponctuelles + catalogue | Services entre membres / mini-économie |
| Réserver / faire / ajuster les points | Classes de personnage |
| Urgence, jour de ménage et boosts | Vue Kanban |
| Jour de ménage (rendez-vous + notification) | Défi collectif du jour de ménage |
| Objectif hebdo + présence | Statistiques avancées |
| Bilan hebdo, XP, niveaux, quelques titres | |
| Jauge de la maison, plan par pièce | |
| Animations de la Casa, des chats et du plumeau ; surprises ; cartons jaunes | |
| Tâches express, animaux, présence « à la maison » | Probabilité d'être à la maison (Wi-Fi, localisation) |
| Cadeaux de niveau, boosts, séries et gels | Widget et tuile Android de présence |
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

1. ~~Formule exacte du bonus de retard~~ : plus de bonus de retard ni de ponctualité.
2. Une tâche faite à plusieurs : points partagés ou doublés ?
3. Durée par défaut d'une réservation : 24 h ? Réglable par tâche ?
4. Objectif hebdo : choisi par chacun, ou valeur commune ?
5. Notifications : lesquelles, à quelle fréquence, et comment ne pas devenir
   agaçant ?
6. Début et fin de semaine : du lundi 00:00 au dimanche 23:59 ? Heure du bilan ?
7. ~~Formule de la jauge de la maison~~ : la tenue à temps des tâches (voir § 7).
8. ~~Seuil du bonus de ponctualité~~ : sans objet (voir § 5).
