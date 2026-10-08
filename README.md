# Companero

Application d'organisation de l'intendance en colocation : tâches récurrentes et
ponctuelles, objectifs hebdomadaires, points et une touche de gamification, autour
de **la Casa**, la maison-personnage dont l'humeur suit la propreté du foyer.

- [Vision produit](docs/vision.md)
- [Direction artistique](docs/direction-artistique.md)
- [Inventaire des tâches](docs/inventaire-taches.md)
- [Mise en production](docs/deploiement.md)

## Stack

- **Symfony 7.4 LTS** (PHP ≥ 8.4), Doctrine ORM, **SQLite** par défaut
  (une coloc = un petit fichier ; PostgreSQL possible via `DATABASE_URL`).
- Front sans build Node : **Twig** + Twig Components, **Turbo** (rafraîchissements
  par morphing) et **Stimulus** via AssetMapper, **Tailwind CSS v4** via
  `symfonycasts/tailwind-bundle`.
- PWA installable (`public/manifest.webmanifest`).
- Appli Android **Hotwire Native** (`android/`), pilotée par **Symfony UX Native**
  (expérimental) : voir [Appli Android](#appli-android).

## Démarrer

```bash
composer install
php bin/console importmap:install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n   # coloc de démo (facultatif)
php bin/console tailwind:build --watch      # dans un terminal à part
symfony serve                               # ou tout serveur PHP pointant sur public/
```

Coloc de démo (la nôtre : zones, tâches du dimanche, chats…) : `leo@example.com` / `companero`
(aussi `lea@`, `robin@`, `gab@`).

Sinon, ouvrez `/bienvenue` pour créer votre coloc, puis partagez le lien
d'invitation affiché dans *Profil › La coloc*.

### Sous Windows, sans rien installer

`dev.cmd` télécharge PHP 8.4, Composer, Java et le SDK Android dans `.tools/`
(supprimer ce dossier désinstalle tout) :

```bat
.\dev setup          :: PHP, dépendances, base de démo, CSS
.\dev serve          :: l'appli sur http://localhost:8000 et sur le Wi-Fi (+ Tailwind en watch)
.\dev test           :: PHPUnit
.\dev apk            :: compile dist\companero.apk
.\dev console …      :: bin/console
.\dev composer …     :: Composer
.\dev deploy moi@serveur /srv/companero      :: met à jour la production (voir docs/deploiement.md)
.\dev publish-apk moi@serveur /srv/companero :: y dépose dist\companero.apk
```

Au premier `.\dev serve`, autorisez `php.exe` dans le pare-feu Windows (réseaux
privés **et** publics si le Wi-Fi est classé « public »), sinon le téléphone ne
joint pas le PC.

### Clôture de la semaine

Les titres de la semaine et le bonus collectif sont distribués par une commande à
lancer chaque lundi peu après minuit ; elle cache aussi les surprises de la nouvelle semaine
dans quelques cartes (à défaut, la première page ouverte de la semaine s'en charge) :

```cron
5 0 * * 1  php /chemin/vers/companero/bin/console app:week:close
```

## Appli Android

Une coquille [Hotwire Native](https://native.hotwired.dev/android/) (Kotlin,
`android/`) affiche les pages du serveur avec une navigation native. Côté Symfony,
[UX Native](https://symfony.com/bundles/ux-native/current/index.html) détecte
l'appli (`ux_is_native()` dans Twig) et sert ses règles de navigation,
déclarées dans `src/Native/NativeConfiguration.php` :

- Accueil, Tâches, Bilan, Profil et la connexion **remplacent** l'écran au lieu de
  s'empiler ;
- les formulaires de tâche s'ouvrent en **modale** (sans barre du bas, la croix
  ferme la modale) ;
- tirer vers le bas rafraîchit la page.

Elle fait aussi vibrer le téléphone pour chaque retour (un clic sous le doigt, les points gagnés,
une boîte qui s'ouvre, un chat qui ronronne) : un *bridge component* Hotwire Native, `haptics`
(`assets/controllers/haptics_controller.js` côté pages, `HapticsComponent.kt` côté appli).
Une version de l'APK sans ce composant ne vibre pas : la recompiler (`.\dev apk`).

L'appli ajoute aussi une tuile **« À la maison »** aux réglages rapides (à côté du
Wi-Fi et du Bluetooth ; à ajouter en modifiant les tuiles) : un appui bascule entre
« Là » et « Pas là », avec la session de l'appli. Si personne n'est connecté ou si
le serveur ne répond pas, elle affiche « Ouvre Companero ».

```bat
.\dev apk                              :: serveur proposé : http://<IP du PC>:8000
.\dev apk https://companero.example    :: ou une autre adresse
```

Pendant que `.\dev serve` tourne, l'APK se télécharge depuis le téléphone sur
`http://<IP du PC>:8000/companero.apk` (autoriser l'installation d'applis inconnues).
Si le serveur ne répond pas, l'appli propose de réessayer ou de changer d'adresse.

En production, écrire la configuration dans `public/native/` à chaque déploiement :

```bash
php bin/console ux:native:build-configs
```

## Qualité

```bash
php bin/phpunit                 # tests unitaires et fonctionnels
vendor/bin/php-cs-fixer fix     # style @Symfony
php bin/console lint:twig templates
```

Les mêmes vérifications tournent sur GitHub Actions à chaque push ; un push sur `main` qui
passe les tests est mis en production automatiquement (voir
[Déploiement automatique](docs/deploiement.md#déploiement-automatique)).

## Organisation du code

| Dossier | Rôle |
|---|---|
| `src/Entity`, `src/Repository` | Modèle : foyer, membres, zones, animaux, tâches, réalisations, journal de points, présence, titres, cadeaux, boosts, surprises, cartons jaunes |
| `src/Task` | Statut d'une tâche (urgence, fraîcheur), tableau des tâches et des pièces, réalisation et correction |
| `src/Presence`, `src/Reminder` | Qui est là (jours par semaine, « à la maison ») et qui prévenir d'une tâche |
| `src/Reward` | Cadeaux de niveau, boosts (dont celui du jour de ménage), cartons jaunes |
| `src/Bounty` | Surprises de la semaine : tirage au hasard dans les cartes, découverte par le premier qui fait la tâche |
| `src/Pet` | Portrait des chats autour de la Casa, d'après leur description |
| `assets/controllers`, `assets/lib/fx.js` | Animations : la Casa et ses chats, jauges qui montent, compteurs, célébrations, confettis |
| `src/Calendar` | La semaine (lundi → dimanche) |
| `src/Progress` | Niveaux, objectifs hebdo proratisés, progression collective |
| `src/Review` | Bilan de la semaine, titres, clôture |
| `src/Household` | Création de la coloc et inscription des membres |
| `src/Controller`, `src/Form`, `templates/` | Interface web |
| `src/Native`, `android/` | Appli Android : règles de navigation et coquille Hotwire Native |
| `scripts/`, `dev.cmd` | Environnement local sous Windows |

Deux notions à ne pas mélanger : les **modèles** (onglet *Modèles* : ce qui revient tout seul,
les express, le catalogue) et les **tâches à faire** (l'accueil : les cartes, avec leur bouton
« C'est fait »). Une tâche qui revient n'est qu'une ligne en base : son modèle, dont la tâche à
faire du moment est calculée. Le bouton + demande d'abord ce qu'on ajoute : une tâche à faire,
ce qu'on a déjà fait, ou un nouveau modèle.

Le statut d'une tâche n'est jamais stocké : il est **calculé** à partir de ses
règles et de sa dernière réalisation. Les points forment un **journal** : chaque
total est une somme de lignes.

## API de l'appli

Deux points d'entrée JSON, authentifiés par la session (cookies de la WebView) :

- `GET /api/presence`, `POST /api/presence` (`{"atHome": true|false}` ou rien pour basculer) :
  « je suis à la maison ». Le `POST` exige l'en-tête `X-Companero-App` (protection CSRF).
- `GET /api/rappels` : les tâches pressantes pour lesquelles on compte sur le membre
  (les siennes, celles d'un coloc absent qu'il remplace, celles des animaux).
