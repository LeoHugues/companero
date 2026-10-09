# Companero — Onboarding

> Ce que fait l'onboarding, et pourquoi. Il complète la [vision produit](vision.md).

## 1. Intention

Aujourd'hui, on crée la coloc ou on la rejoint, puis on tombe directement sur l'accueil. L'objectif
perso reste à 70 points sans qu'on l'ait choisi, la présence à 7 jours, tous les rappels sont
activés, et rien n'explique la Casa, les cartes ni les objectifs.

L'onboarding doit :

- dire **à quoi sert l'appli** : gérer l'intendance de la maison, s'organiser ensemble, se motiver ;
- expliquer **comment marchent les cartes**, et surtout le **glissement** ;
- présenter **les deux objectifs**, celui de la maison et celui de chacun ;
- poser **la charte de la coloc**, les règles de bon sens pour vivre ensemble ;
- faire **régler à chacun** son objectif, sa présence et ses rappels.

On garde le mot « tâche ». Pas de classement, pas de contrôle : on n'en parle pas.

## 2. Qui le voit

| Qui | Quand |
|---|---|
| Celui qui crée la coloc (`/bienvenue`) | Juste après la création |
| Celui qui rejoint (`/rejoindre/{token}`) | Juste après l'étape « Qui es-tu ? » |
| Les membres déjà inscrits | À leur prochaine visite, une seule fois |

**Il est obligatoire, et court.** Tant qu'il n'est pas terminé, toute page de l'appli y ramène
(`RequireOnboardingListener`), sauf la déconnexion et ce que l'appli Android demande toute seule
(`/api`). Interrompu, il reprend au début ; l'adhésion à la charte, elle, est gardée.

On peut **relire la partie A** à tout moment depuis le Profil (« Comment ça marche »), et la
**charte** depuis les réglages de la coloc.

## 3. Le déroulé

Chaque écran est raconté par la Casa (sa bulle BD), avec une idée par écran, des points de
progression en haut et un bouton « Suivant » en bas. On peut revenir en arrière.

### 0. Qui es-tu ? (uniquement quand on rejoint)

> « Bienvenue ! On t'attendait. Tu es… ? »

- Les **profils à réclamer** de la coloc, en grands boutons avec leur avatar et leur couleur :
  « Léa », « Robin », « Gab ».
- « **Je suis quelqu'un d'autre** » : on saisit un prénom, et un nouveau profil est créé.
- Dans tous les cas, on choisit ensuite **un pseudo et un mot de passe**. Pas d'e-mail : le pseudo
  suffit pour se connecter, sans tenir compte des majuscules (« Léo » ou « léo »).

Réclamer un profil, c'est récupérer **tout ce qui y est attaché** : réalisations, points, niveau,
tâches dont on a la charge, animaux dont on est le maître, couleur.

Sans profil à réclamer, l'étape se réduit au formulaire actuel.

### A. Comprendre

**A1. L'esprit**

> « Salut, moi c'est la Casa ! Ici, on s'occupe de l'intendance de la maison : le ménage, le linge,
> les poubelles, le jardin, les animaux, les petites réparations… Je vous aide à vous organiser
> ensemble, à voir ce qui attend, et à vous motiver un peu. Plus vous prenez soin de moi, plus je
> rayonne. »

La Casa passe de poussiéreuse à rayonnante pendant que la bulle s'affiche.

**A2. Une carte**

> « Chaque chose à faire est une carte. Elle dit ce qu'il faut faire, où, et ce que ça rapporte. »

Une **fausse carte** qu'on peut toucher, présentée avec trois repères :

- **le médaillon** : la catégorie, et un anneau qui se vide avec le temps ;
- **« Je prends »** : je dis aux autres que je m'en occupe ;
- **« C'est fait »** : les étoiles, les points, et la Casa réagit.

**A3. Le glissement**

> « La plupart des tâches glissent : elles n'ont pas de date, elles ont un rythme. L'aspirateur ou
> le coup de balai du salon, c'est environ tous les 2 jours. Plus le temps passe depuis la dernière
> fois, plus la carte devient pressante. »

Un **curseur « depuis la dernière fois »** (de 0 à 4 jours, par demi-journée) fait vivre une carte
« Aspirateur ou balai au salon, tous les 2 jours » :

| Depuis | État de la carte |
|---|---|
| 0 à 1 jour | fraîche (sauge), anneau plein |
| 1 jour et demi | bientôt (miel) |
| 2 jours | due |
| 3 jours et plus | en retard (corail) |

Un bouton « C'est fait » sur la carte la remet à zéro, et le curseur revient à 0.

> « Pas besoin d'attendre qu'elle soit due : on peut la faire quand on veut, ça remet le compteur à
> zéro. »

**A4. Les autres rythmes**

> « Certaines tâches marchent autrement. »

Une ligne, une icône et un exemple pour chaque rythme :

- **À jour fixe** : les poubelles, le mardi soir. Elle revient à date fixe, avec une heure limite.
- **Avec un engagement** : la serpillière, au moins 1 fois par semaine. Elle glisse comme les
  autres, mais si elle n'est pas faite dans la semaine, elle devient due le **jour de ménage**
  (ici : *le jour de la coloc*).
- **En un geste** : vider le lave-vaisselle. Jamais planifiée : on la signale d'un appui quand on
  l'a faite.
- **Ça arrive !** : nettoyer le vomi du chat. Elle dort jusqu'à ce que quelqu'un la signale, et
  alors il faut la faire vite.
- **Une seule fois** : appeler le proprio. Une fois faite, elle disparaît.

> « Et parfois, une carte cache une surprise. La première personne qui la fait la trouve. »

**A5. Les points**

> « Chaque tâche rapporte des points : environ 10 points pour 5 minutes, un peu plus quand c'est
> une corvée. Le jour de ménage, tout rapporte un peu plus. Les points, c'est vous qui les réglez :
> si un barème vous semble injuste, changez-le. »

**A6. Les deux objectifs**

> « Il y a deux objectifs chaque semaine. »

- **L'objectif de la maison** (sa vraie valeur, par exemple 250 pts) : les points de tout le monde
  comptent. S'il est atteint, chaque membre présent gagne +50 XP (pour le niveau, pas pour l'objectif perso). Il se règle dans les réglages de
  la coloc.
- **Ton objectif** : ce que tu vises toi, au prorata de tes jours de présence. Une série compte les
  semaines où tu l'atteins.

Tu le choisis à l'étape suivante.

**A7. La charte de la coloc**

> « Avant de commencer, les règles de la maison. Rien à voir avec l'appli : c'est juste comme ça
> qu'on vit bien ensemble. »

La liste des règles de **cette** coloc (voir § 4), puis un bouton **« Ça me va »**, qui enregistre
l'adhésion avec sa date.

### B. Se régler

Un seul écran, trois blocs :

1. **Mon objectif de la semaine** : 5, 10, 15 ou 20 minutes par jour (70, 140, 210, 280 pts).
   Une aide : « Pour que la maison atteigne ses 250 pts à 4, ça fait environ 60 pts chacun.
   Commence petit, tu pourras monter. » Aucun choix n'est fait d'avance : il faut en toucher un.
2. **Je suis là combien de jours par semaine ?** Le curseur de 0 à 7.
3. **Mes rappels** : les trois interrupteurs du Profil. Dans l'appli Android, on demande juste
   après l'autorisation des notifications.

Bouton **« C'est parti »** : l'onboarding est marqué terminé, on arrive sur l'accueil, et la Casa
fait la fête.

## 4. La charte de la coloc

Une liste de règles **propre à chaque coloc**, modifiable par tout le monde dans *Réglages de la
coloc › La charte* : ajouter, modifier, supprimer, réordonner. Chaque règle a un texte court et, en
option, un « Pourquoi ? » qui se déplie.

Une nouvelle coloc démarre avec ces règles :

1. Je fais ma vaisselle quand j'ai fini, pas « plus tard ».
2. Avant d'aller dormir, je fais un tour : chaussures, pulls, table. Rien ne traîne de mon côté.
3. **On fait pipi assis.**
   *Pourquoi ?* Debout, on est environ cinq fois plus loin de la cuvette, le jet se fragmente en
   gouttelettes et éclabousse bien plus loin qu'on ne le croit : la physique l'a montré (Splash Lab
   de BYU, Truscott et Hurd, « Urinal Dynamics », American Physical Society, 2013). Côté santé,
   pas de différence chez les hommes en bonne santé ; assis, la vessie se vide mieux en cas de
   troubles de la prostate (de Jong et al., *PLOS ONE*, 2014). C'est donc une affaire de propreté,
   et c'est celle de tout le monde.
4. Ce que je finis, je le remplace : papier toilette, sel, éponge… ou je le signale.
5. Mes affaires ne s'installent pas dans les pièces communes.
6. Si je ne peux pas faire ce que j'avais pris, je préviens, ou je passe en « Pas là ».

**Adhésion.** Chaque membre adhère à l'étape A7. La page de la charte montre à qui elle va.
Quand une règle est ajoutée, réécrite ou retirée, celui qui l'a changée y adhère d'office ; les
autres voient sur l'accueil « La charte de la coloc a changé », jusqu'à ce qu'ils la relisent et
disent « Ça me va ». Rien n'est bloqué pour autant. Changer l'ordre des règles ne demande rien à
personne.

Sources de la règle 3 :

- [BYU Urine Scientists Solve Splash-Back Problem](https://m.cityweekly.net/BuzzBlog/archives/2013/11/11/byu-urine-scientists-solve-splash-back-problem) (City Weekly, 2013)
- [Science Addresses The Problem Of Pee Splashback](https://www.popsci.com/article/science/science-addresses-problem-pee-splashback/) (Popular Science, 2013)
- de Jong et al., [Urinating Standing versus Sitting: Position Is of Influence in Men with Prostate Enlargement](https://pmc.ncbi.nlm.nih.gov/articles/PMC4106761/) (*PLOS ONE*, 2014)

## 5. Les profils à réclamer

Un membre peut exister **sans compte** : un prénom, une couleur, et rien d'autre. Il peut avoir des
tâches, être maître d'un animal, apparaître dans les barres collectives. Il ne peut pas se connecter.

- **Créer** : *Réglages de la coloc › Les colocs › Ajouter quelqu'un*, avec le prénom seulement.
  Pratique pour tout préparer avant d'envoyer le lien.
- **Réclamer** : l'étape 0 de l'onboarding. On choisit son profil, puis un pseudo et un mot de
  passe.
- **Libérer** un compte existant, pour réparer une erreur ou remettre en état la prod :
  `bin/console app:membre:a-reclamer <pseudo ou prénom>…`. La commande efface le pseudo et le mot
  de passe, et garde tout le reste. Elle refuse de libérer le dernier membre qui a un compte.

**Sécurité.** N'importe qui avec le lien d'invitation peut réclamer un profil libre. C'est le même
niveau de confiance qu'aujourd'hui : le lien suffit déjà pour entrer dans la coloc. Un profil
réclamé ne l'est plus pour personne d'autre.

**Mon compte.** *Profil › Mon compte* change son pseudo et son mot de passe (le mot de passe
actuel est demandé).

## 6. Données

- `Member.username` (nullable, unique, en minuscules) remplace l'e-mail : sans pseudo, le profil
  est à réclamer. La migration prend ce qui précède le @ de l'ancien e-mail (`leo@…` devient `leo`).
- `Member.onboardedAt` : la fin de l'onboarding. Les membres existants démarrent à `null`, et
  passent donc par la découverte à leur prochaine visite.
- `Member.charterAcceptedAt` et `Household.charterUpdatedAt` : on a adhéré à la charte telle
  qu'elle est si on l'a fait après son dernier changement.
- `CharterRule` : `household`, `text`, `why` (facultatif), `position`. Les règles par défaut
  (`App\Household\Charter`) sont créées avec chaque coloc, et par la migration pour les colocs
  existantes.

## 7. Hors périmètre

- Les bulles d'aide contextuelles (« première visite sur la page Plan… »).
- Un assistant de mise en place pour le créateur (pièces, animaux, objectif de la maison) : les
  valeurs par défaut et les réglages suffisent pour l'instant.
- Le renommage de « tâche » en « quête ».

## 8. Décisions

1. Une charte modifiée est signalée sur l'accueil à ceux qui n'y ont pas encore adhéré ; elle
   ne bloque rien.
2. « Qui es-tu ? » n'apparaît que pour rejoindre une coloc qui a des profils à réclamer.
3. Les explications (A1 à A6) se relisent depuis *Profil › Comment ça marche* ; la charte, depuis
   *Réglages de la coloc › La charte*.
