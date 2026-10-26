# Companero — Onboarding

> Ce que fait l'onboarding, et pourquoi. Il complète la [vision produit](vision.md).

## 1. Intention

Arriver dans Companero doit être accueillant : la Casa reçoit, une question à la fois, et la maison est
prête avant même qu'on la voie. L'onboarding doit :

- **ouvrir les bonnes portes** : créer sa coloc, la rejoindre par son lien (même collé), se reconnecter ;
- **mettre la maison en place** pour celui qui la crée : ses pièces, ses animaux, ses premières tâches, et
  l'invitation des autres ;
- dire **à quoi sert l'appli** : gérer l'intendance de la maison, s'organiser ensemble, se motiver ;
- expliquer **comment marchent les cartes**, et surtout le **glissement** ;
- présenter **les deux objectifs**, celui de la maison et celui de chacun ;
- poser **la charte de la coloc**, les règles de bon sens pour vivre ensemble ;
- faire **régler à chacun** son objectif, sa présence et ses rappels.

On garde le mot « tâche ». Pas de classement, pas de contrôle : on n'en parle pas.

## 2. Qui le voit

| Qui | Par où | Puis |
|---|---|---|
| Celui qui crée la coloc | L'accueil › « Créer notre coloc » (`/bienvenue`) | La mise en place, puis la découverte |
| Celui qui rejoint | Le lien d'invitation (`/rejoindre/{token}`), ouvert ou collé sur l'accueil | La découverte |
| Les membres déjà inscrits | À leur prochaine visite, une seule fois | La découverte |

**La découverte est obligatoire, et courte.** Tant qu'elle n'est pas terminée, toute page de l'appli y ramène
(`RequireOnboardingListener`), sauf la déconnexion, l'accueil, la mise en place et ce que l'appli Android demande
toute seule (`/api`). Interrompue, elle reprend au début ; l'adhésion à la charte, elle, est gardée.

On peut **relire la partie A** à tout moment depuis le Profil (« Comment ça marche »), et la
**charte** depuis les réglages de la coloc.

## 3. Le déroulé

Chaque écran est raconté par la Casa (sa bulle BD), avec une idée par écran, où l'on en est en haut et
« Suivant » en bas. On peut revenir en arrière.

### L'accueil (`/connexion`)

C'est là que s'ouvre l'appli tant qu'on n'est pas connecté. La Casa dit bonjour, une phrase dit ce qu'est
Companero (« Toute la coloc, au même endroit »), puis trois portes :

- **Créer notre coloc** : « La maison est prête en deux minutes. »
- **J'ai un lien d'invitation** : se déplie sur un champ où **coller le lien**. Dans l'appli Android, un lien
  ouvre le navigateur, pas l'appli : on le colle donc ici. Le message entier peut être collé, le lien y est
  retrouvé (`/rejoindre?lien=…`). Un lien qui ne mène à aucune coloc revient ici, le champ ouvert et l'erreur dite.
- **Déjà un compte ?** : pseudo et mot de passe. Après une erreur, la connexion passe en premier.

### Créer la coloc (`/bienvenue`)

Trois questions, une à la fois (`steps_controller.js`), la Casa reprenant ce qu'on vient d'écrire :

1. le nom de la coloc (« « Les Lilas », j'adore ! ») ;
2. son prénom ;
3. un pseudo (proposé d'après le prénom : « Léa Martin » donne `lea.martin`) et un mot de passe.

« Entrée » passe à la question suivante. Revenu avec une erreur, le formulaire s'ouvre sur la question qui en a
une. Sans JavaScript, les trois questions s'affichent l'une sous l'autre.

### La mise en place (seulement pour celui qui crée)

Quatre segments en haut (La coloc, La maison, Pour commencer, Les colocs), la Casa en petit qui raconte :

1. **La maison** (`/bienvenue/maison`) : les pièces, en puces (les habituelles cochées, une autre à écrire),
   les animaux (un nom et une espèce), le jour de ménage.
2. **Pour commencer** (`/bienvenue/pour-commencer`) : les tâches que font presque toutes les colocs
   (`App\Household\StarterKit`), chacune avec son rythme, ses pièces et ses points, les habituelles cochées.
   N'apparaissent que celles des pièces et des animaux de la maison ; un animal seul est nommé (« Changer la
   litière de Tishka »). Chaque tâche cochée devient une carte, et leurs premières échéances s'étalent
   (`Task.firstDueAt`) : les plus fréquentes (tous les 3 jours ou moins) tout de suite, les autres le jour de
   ménage, les plus rares une semaine après. La maison démarre avec de quoi faire, et la Casa sourit.
3. **Les colocs** (`/bienvenue/inviter`) : le lien d'invitation, à envoyer par le partage du téléphone,
   WhatsApp ou copié, et les profils à préparer par leur prénom (chacun le réclamera avec le lien).

Une fois les tâches créées, la mise en place est derrière : on n'y revient plus (la maison et le lien se
règlent ensuite dans les réglages de la coloc).

### 0. Rejoindre

> « Bienvenue chez Les Lilas ! Tu t'appelles comment ? »

- S'il y a des **profils à réclamer**, ils viennent d'abord, en grands boutons avec leur avatar :
  « Je suis Léa », « Je suis Robin ». Puis « **Je suis quelqu'un d'autre** ».
- Quelqu'un de nouveau répond à deux questions, une à la fois comme à la création : son prénom (on voit qui est
  déjà là), puis **un pseudo et un mot de passe**. Pas d'e-mail : le pseudo suffit pour se connecter, sans
  tenir compte des majuscules (« Léo » ou « léo »).
- Un lien inconnu (mal copié, ou remplacé par « Nouveau lien ») mène à une page qui le dit gentiment, et où
  coller le bon.
- Déjà connecté, on revient à l'accueil de sa coloc.

Réclamer un profil, c'est récupérer **tout ce qui y est attaché** : réalisations, points, niveau,
tâches dont on a la charge, animaux dont on est le maître, couleur.

### A. Comprendre

**A1. L'esprit**

> « Salut Léo ! Moi, c'est la Casa. »

La grande Casa, rayonnante. Ceux avec qui on vit (leurs avatars, « Avec Robin et Inès »), puis ce qu'il y a
dans l'appli : les tâches en cartes, les courses, les comptes, et la Casa, qui rayonne quand la maison brille.

**A2. Une tâche, une carte**

> « Chaque chose à faire est une carte. Essaie celle-ci : elle ne compte pas pour de vrai. »

La plupart des tâches **glissent** : elles n'ont pas de date, elles ont un rythme. Une **fausse carte**, la même
que les vraies, « Aspirateur ou balai au salon, tous les 2 jours », et un **curseur « Le temps passe… »** (de 0 à
4 jours, par demi-journée) qui la fait vieillir :

| Depuis | État de la carte |
|---|---|
| 0 à 1 jour | fraîche (sauge), le fil plein |
| 1 jour et demi | bientôt (miel) |
| 2 jours | due |
| 3 jours et plus | en retard (corail) |

Puis ses repères : **« Je prends »** (les autres voient que je m'en occupe), **« C'est fait »** (ses points sont
à moi, elle repart à zéro ; pas besoin d'attendre qu'elle soit due, et en retard elle rapporte autant),
**toucher la carte** (sa page, ses réglages).

**A3. Les autres rythmes**

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

**A4. Les points et les objectifs**

> « Chaque tâche rapporte des points. Pas pour se comparer : pour avancer ensemble. »

Environ 10 points pour 5 minutes, un peu plus quand c'est une corvée. Le jour de ménage, tout rapporte un peu
plus. Les points, c'est vous qui les réglez : si un barème vous semble injuste, changez-le. Puis les deux
objectifs de la semaine :

- **Celui de la maison** (sa vraie valeur, par exemple 250 pts) : les points de tout le monde
  comptent. S'il est atteint, chaque membre présent gagne +50 XP (pour le niveau, pas pour l'objectif perso).
  Il se règle dans les réglages de la coloc.
- **Le tien** : ce que tu vises toi, au prorata de tes jours de présence. Une série compte les
  semaines où tu l'atteins. Tu le choisis juste après.

**A5. La charte de la coloc**

> « Avant de commencer, les règles de la maison. Rien à voir avec l'appli : c'est juste comme ça
> qu'on vit bien ensemble. »

La liste des règles de **cette** coloc (voir § 4), une invitation (« Elle est à vous » : une règle
manque, une autre est de trop ou mal dite ? on l'ajoute, on la réécrit, ou on en parle avec les
autres), puis un bouton **« Ça me va »**, qui enregistre l'adhésion avec sa date.

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
coloc › La charte* : ajouter, modifier, supprimer, et réordonner sur place avec les flèches ▲ ▼ de
chaque règle. Chaque règle a un texte court et, en option, un « Pourquoi ? » qui se déplie.

Une nouvelle coloc démarre avec ces règles :

1. Je fais ma vaisselle quand j'ai fini, pas « plus tard ».
2. Avant de faire la vaisselle, je vide l'égouttoir : jamais de vaisselle mouillée sur de la vaisselle
   sèche.
3. Quand j'ai fini avec l'éponge, je la rince et je l'essore.
4. L'évier n'est pas une poubelle : les fins d'assiette vont à la poubelle (ou au compost). Et si ça
   arrive quand même, je nettoie derrière moi.
5. Avant d'aller dormir, je fais un tour : chaussures, pulls, table. Rien ne traîne de mon côté.
6. **On fait pipi assis.**
   *Pourquoi ?* Debout, on est environ cinq fois plus loin de la cuvette, le jet se fragmente en
   gouttelettes et éclabousse bien plus loin qu'on ne le croit : la physique l'a montré (Splash Lab
   de BYU, Truscott et Hurd, « Urinal Dynamics », American Physical Society, 2013). Côté santé,
   pas de différence chez les hommes en bonne santé ; assis, la vessie se vide mieux en cas de
   troubles de la prostate (de Jong et al., *PLOS ONE*, 2014). C'est donc une affaire de propreté,
   et c'est celle de tout le monde.
7. Ce que je finis, je le remplace : papier toilette, sel, éponge… ou je le signale.
8. Mes affaires ne s'installent pas dans les pièces communes.
9. Si je ne peux pas faire ce que j'avais pris, je préviens, ou je passe en « Pas là ».

**Adhésion.** Chaque membre adhère à l'étape A5. La page de la charte montre à qui elle va.
Quand une règle est ajoutée, réécrite ou retirée, celui qui l'a changée y adhère d'office ; les
autres voient sur l'accueil « La charte de la coloc a changé », jusqu'à ce qu'ils la relisent et
disent « Ça me va ». Rien n'est bloqué pour autant. Changer l'ordre des règles ne demande rien à
personne.

Sources de la règle 6 :

- [BYU Urine Scientists Solve Splash-Back Problem](https://m.cityweekly.net/BuzzBlog/archives/2013/11/11/byu-urine-scientists-solve-splash-back-problem) (City Weekly, 2013)
- [Science Addresses The Problem Of Pee Splashback](https://www.popsci.com/article/science/science-addresses-problem-pee-splashback/) (Popular Science, 2013)
- de Jong et al., [Urinating Standing versus Sitting: Position Is of Influence in Men with Prostate Enlargement](https://pmc.ncbi.nlm.nih.gov/articles/PMC4106761/) (*PLOS ONE*, 2014)

## 5. Les profils à réclamer

Un membre peut exister **sans compte** : un prénom, une couleur, et rien d'autre. Il peut avoir des
tâches, être maître d'un animal, apparaître dans les barres collectives. Il ne peut pas se connecter.

- **Créer** : à la mise en place (« Les colocs »), ou dans *Réglages de la coloc › Les colocs › Préparer le
  profil d'un coloc*, avec le prénom seulement (deux colocs ne portent pas le même). Pratique pour tout
  préparer avant d'envoyer le lien.
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
- L'objectif de la maison à la mise en place : sa valeur par défaut suffit, il se règle ensuite.
- Les liens qui ouvrent l'appli Android (deep links) : on colle le lien sur l'accueil.
- Le renommage de « tâche » en « quête ».

## 8. Décisions

1. Une charte modifiée est signalée sur l'accueil à ceux qui n'y ont pas encore adhéré ; elle
   ne bloque rien.
2. « Qui es-tu ? » n'apparaît que pour rejoindre une coloc qui a des profils à réclamer.
3. Les explications (A1 à A4) se relisent depuis *Profil › Comment ça marche* ; la charte, depuis
   *Réglages de la coloc › La charte*.
