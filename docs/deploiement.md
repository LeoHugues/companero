# Mise en production

Companero est une appli Symfony classique : PHP-FPM derrière un serveur web, une base
SQLite dans `var/`, deux tâches planifiées. L'appli Android pointe ensuite vers l'adresse
publique.

## Ce qu'il faut sur le serveur

- **PHP 8.4** en CLI et en FPM, avec les extensions `pdo_sqlite`, `intl`, `mbstring`,
  `ctype`, `iconv` et `xml` (sous Debian/Ubuntu : `php8.4-fpm php8.4-cli php8.4-sqlite3
  php8.4-intl php8.4-mbstring php8.4-xml`, depuis le dépôt de Sury/Ondřej si besoin).
- **Composer** et **git**.
- Un **serveur web** : Caddy (le plus simple, HTTPS automatique) ou nginx. Les exemples sont
  dans [`deploy/Caddyfile`](../deploy/Caddyfile) et [`deploy/nginx.conf`](../deploy/nginx.conf).
- Un **nom de domaine** qui pointe sur le serveur, pour le HTTPS.
- L'accès sortant à GitHub et à jsDelivr pendant le déploiement (Tailwind et les
  bibliothèques JS sont téléchargés).
- L'heure du serveur réglée sur celle de la coloc (`timedatectl set-timezone Europe/Paris`) :
  la semaine se clôt le lundi à minuit.

## Première installation

Avec l'utilisateur qui fait tourner PHP-FPM (souvent `www-data`), pour que l'appli puisse
écrire dans `var/` :

```bash
sudo mkdir -p /srv/companero && sudo chown www-data: /srv/companero
sudo -u www-data git clone https://github.com/LeoHugues/companero.git /srv/companero
cd /srv/companero

# La configuration de production, jamais versionnée :
sudo -u www-data tee .env.local > /dev/null <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(openssl rand -hex 32)
EOF

sudo -u www-data ./scripts/deploy.sh
```

La base `var/data_prod.db` est créée par les migrations. Il n'y a pas de données de démo en
production : ouvrez `https://votre-domaine/bienvenue` pour créer la coloc, puis partagez le
lien d'invitation (*Réglages de la coloc › Inviter quelqu'un*).

Ensuite :

1. **Serveur web** : copiez et adaptez `deploy/Caddyfile` (ou `deploy/nginx.conf` puis
   `certbot --nginx`), puis rechargez le serveur.
2. **Tâches planifiées** : ajoutez le contenu de [`deploy/crontab`](../deploy/crontab) au
   crontab de `www-data` (`sudo crontab -u www-data -e`) : clôture de la semaine le lundi à
   0 h 05, sauvegarde de la base chaque nuit.
3. **Appli Android** : compilez-la avec l'adresse publique, puis déposez-la sur le serveur
   pour que les colocs la téléchargent sur `https://votre-domaine/companero.apk` :

   ```bat
   .\dev apk https://votre-domaine
   .\dev publish-apk moi@serveur /srv/companero
   ```

## Mettre à jour

Sur le serveur : `sudo -u www-data /srv/companero/scripts/deploy.sh`. Depuis Windows :

```bat
.\dev deploy moi@serveur /srv/companero
```

Le script sauvegarde la base, récupère `main` (ou la version passée en argument : un tag,
un commit), installe les dépendances, compile les assets, applique les migrations et vide
le cache. Il s'arrête à la première erreur ; la sauvegarde faite juste avant est dans
`var/backups/`.

Pour déployer une autre branche : `DEPLOY_BRANCH=ma-branche ./scripts/deploy.sh`.

## Sauvegardes

`php bin/console app:backup` écrit une copie cohérente de la base dans `var/backups/` (même
pendant que l'appli tourne) et garde les 30 dernières. Pensez à copier ce dossier hors du
serveur de temps en temps.

Restaurer : arrêter PHP-FPM, remplacer `var/data_prod.db` par une sauvegarde, relancer.

## Derrière un autre proxy

Si un autre serveur (ou un conteneur) relaie les requêtes vers celui de Companero, donnez son
adresse dans `.env.local` pour que Symfony fasse confiance à ses en-têtes `X-Forwarded-*`
(HTTPS, adresse du client) :

```bash
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8
```
