#!/usr/bin/env bash
# Met à jour Companero sur le serveur de production. À lancer sur le serveur, dans le dossier
# de l'appli, avec l'utilisateur qui fait tourner PHP (www-data…) :
#
#   ./scripts/deploy.sh            # dernière version de main (ou de $DEPLOY_BRANCH)
#   ./scripts/deploy.sh v1.2.0     # une version précise (tag ou commit)
#
# Lancé en root, il se relance avec le propriétaire du dossier, pour que var/ reste à PHP.
#
# Depuis Windows : .\dev deploy utilisateur@serveur /chemin/vers/companero
set -euo pipefail

cd "$(dirname "$0")/.."

owner=$(stat -c %U .)
if [ "$(id -u)" -eq 0 ] && [ "$owner" != root ]; then
    exec sudo -u "$owner" -- "$PWD/scripts/deploy.sh" "$@"
fi

export APP_ENV=prod APP_DEBUG=0

step() { printf '\033[36m==> %s\033[0m\n' "$*"; }

if ! grep -qs '^APP_ENV=prod' .env.local; then
    echo "Il manque .env.local (APP_ENV=prod, APP_SECRET…) : voir docs/deploiement.md" >&2
    exit 1
fi

step 'Sauvegarde de la base'
if [ -d vendor ]; then php bin/console app:backup --keep=30 --no-interaction; fi

step 'Récupération du code'
git fetch --tags --prune origin
if [ $# -gt 0 ] && [ -n "$1" ]; then
    git checkout --quiet "$1"
else
    git checkout --quiet "${DEPLOY_BRANCH:-main}"
    git pull --ff-only --quiet origin "${DEPLOY_BRANCH:-main}"
fi
echo "Version : $(git describe --tags --always)"

step 'Dépendances'
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
php bin/console importmap:install

step 'Assets'
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console ux:native:build-configs

step 'Base de données'
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

step 'Cache'
php bin/console cache:clear
php bin/console cache:warmup

printf '\033[32mCompanero est à jour.\033[0m\n'
