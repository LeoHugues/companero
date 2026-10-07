# Companero

Application d'organisation de l'intendance en colocation : tâches récurrentes et
ponctuelles, objectifs hebdomadaires, points et une touche de gamification, autour
de **la Casa**, la maison-personnage dont l'humeur suit la propreté du foyer.

- [Vision produit](docs/vision.md)
- [Direction artistique](docs/direction-artistique.md)
- [Inventaire des tâches](docs/inventaire-taches.md)

## Stack

- **Symfony 7.4 LTS** (PHP ≥ 8.2), Doctrine ORM, **SQLite** par défaut
  (une coloc = un petit fichier ; PostgreSQL possible via `DATABASE_URL`).
- Front sans build Node : **Twig** + Twig Components, **Turbo** (rafraîchissements
  par morphing) et **Stimulus** via AssetMapper, **Tailwind CSS v4** via
  `symfonycasts/tailwind-bundle`.
- PWA installable (`public/manifest.webmanifest`).

## Démarrer

```bash
composer install
php bin/console importmap:install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n   # coloc de démo (facultatif)
php bin/console tailwind:build --watch      # dans un terminal à part
symfony serve                               # ou tout serveur PHP pointant sur public/
```

Coloc de démo : `leo@example.com` / `companero` (aussi `ines@`, `max@`, `sam@`).

Sinon, ouvrez `/bienvenue` pour créer votre coloc, puis partagez le lien
d'invitation affiché dans *Profil › La coloc*.

### Clôture de la semaine

Les titres de la semaine et le bonus collectif sont distribués par une commande à
lancer chaque lundi peu après minuit :

```cron
5 0 * * 1  php /chemin/vers/companero/bin/console app:week:close
```

## Qualité

```bash
php bin/phpunit                 # tests unitaires et fonctionnels
vendor/bin/php-cs-fixer fix     # style @Symfony
php bin/console lint:twig templates
```

## Organisation du code

| Dossier | Rôle |
|---|---|
| `src/Entity`, `src/Repository` | Modèle : foyer, membres, zones, tâches, réalisations, journal de points, absences, titres |
| `src/Task` | Statut d'une tâche (urgence, fraîcheur), bonus, tableau des tâches, réalisation |
| `src/Calendar` | La semaine (lundi → dimanche) |
| `src/Progress` | Niveaux, objectifs hebdo proratisés, progression collective |
| `src/Review` | Bilan de la semaine, titres, clôture |
| `src/Household` | Création de la coloc et inscription des membres |
| `src/Controller`, `src/Form`, `templates/` | Interface web |

Le statut d'une tâche n'est jamais stocké : il est **calculé** à partir de ses
règles et de sa dernière réalisation. Les points forment un **journal** : chaque
total est une somme de lignes.
