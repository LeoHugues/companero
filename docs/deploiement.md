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
DEFAULT_URI=https://votre-domaine
EOF

sudo -u www-data ./scripts/deploy.sh
```

La base `var/data_prod.db` est créée par les migrations, vide : pas de données de démo en
production. Créez la coloc et votre compte à partir de sa description
([`config/coloc/notre-coloc.yaml`](../config/coloc/notre-coloc.yaml) : zones et plan, Tishka,
le ménage du dimanche, les tâches express, le catalogue) :

```bash
sudo -u www-data php bin/console app:coloc:creer config/coloc/notre-coloc.yaml --nom=Léo --pseudo=leo
```

Le mot de passe est demandé, puis la commande affiche le lien d'invitation à envoyer aux
autres colocs (il est aussi dans *Réglages de la coloc › Inviter quelqu'un*). Tout se modifie
ensuite dans l'appli. On s'y connecte avec un **pseudo** (`leo`, peu importe les majuscules) et
un mot de passe.

Pour tout préparer avant que les autres arrivent (leurs tâches, leurs animaux), ajoutez-les par
leur prénom dans *Réglages de la coloc › Préparer le profil d'un coloc* : avec le lien
d'invitation, chacun choisit son profil (« Je suis Robin ») et y retrouve tout. Un compte créé
pour quelqu'un d'autre se remet « à réclamer » sans rien perdre (points, tâches, animaux) :

```bash
sudo -u www-data php bin/console app:membre:a-reclamer Gab Robin Léa
``` Pour une coloc sans fichier de description, `https://votre-domaine/bienvenue`
la crée avec quelques zones et un catalogue par défaut.

Ensuite :

1. **Serveur web** : copiez et adaptez `deploy/Caddyfile` (ou `deploy/nginx.conf` puis
   `certbot --nginx`), puis rechargez le serveur.
2. **Tâches planifiées** : clôture de la semaine le lundi à 0 h 05, sauvegarde de la base
   chaque nuit. Avec cron, ajoutez le contenu de [`deploy/crontab`](../deploy/crontab) au
   crontab de `www-data` (`sudo crontab -u www-data -e`). Sans cron (Debian minimal…), les
   timers systemd de [`deploy/systemd/`](../deploy/systemd/) font la même chose, à l'heure
   de Paris même si le serveur est en UTC :

   ```bash
   sudo cp deploy/systemd/companero-* /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable --now companero-week-close.timer companero-backup.timer
   ```
3. **Appli Android** : la CI la compile et la publie sur `https://votre-domaine/companero.apk`
   (voir [L'appli Android](#lappli-android)). À la main, sans la CI :

   ```bat
   .\dev apk https://votre-domaine
   .\dev publish-apk moi@serveur /srv/companero
   ```

## Mettre à jour

Sur le serveur : `sudo -u www-data /srv/companero/scripts/deploy.sh`. Depuis Windows :

```bat
.\dev deploy moi@serveur /srv/companero
```

Lancé en root, le script se relance avec le propriétaire du dossier (`www-data`) : les
fichiers qu'il crée restent modifiables par PHP.

Le script sauvegarde la base, récupère `main` (ou la version passée en argument : un tag,
un commit), installe les dépendances, compile les assets, applique les migrations et vide
le cache. Il s'arrête à la première erreur ; la sauvegarde faite juste avant est dans
`var/backups/`.

Pour déployer une autre branche : `DEPLOY_BRANCH=ma-branche ./scripts/deploy.sh`.

## Déploiement automatique

Le workflow GitHub Actions [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) lance
les tests (PHPUnit, php-cs-fixer, lint des templates) à chaque push, et compile l'appli Android
quand elle a changé. Sur `main`, une fois tout au vert, il se connecte au serveur en SSH et lance
`scripts/deploy.sh` sur le commit testé, avec la nouvelle appli s'il y en a une (voir
[L'appli Android](#lappli-android)). Les déploiements passent un par un, dans l'ordre des pushes.
Si les tests ou la compilation échouent, rien n'est déployé.

Une seule fois, sur le serveur : un utilisateur `deploy` qui a le droit de lancer le
script en tant que `www-data` (qui, lui, n'a pas de shell), et une clé SSH réservée au déploiement
(si `deploy` existe déjà, sautez `adduser`) :

```bash
sudo adduser --disabled-password --gecos '' deploy
echo 'deploy ALL=(www-data) NOPASSWD: /srv/companero/scripts/deploy.sh' | sudo tee /etc/sudoers.d/companero-deploy
sudo chmod 440 /etc/sudoers.d/companero-deploy

ssh-keygen -t ed25519 -N '' -C companero-deploy -f companero-deploy
sudo install -d -o deploy -g deploy -m 700 ~deploy/.ssh
sudo tee -a ~deploy/.ssh/authorized_keys < companero-deploy.pub > /dev/null
sudo chown deploy: ~deploy/.ssh/authorized_keys
ssh-keyscan votre-domaine   # pour le secret DEPLOY_KNOWN_HOSTS
```

Puis dans GitHub, *Settings › Secrets and variables › Actions* :

| Secret | Valeur |
|---|---|
| `DEPLOY_HOST` | l'adresse du serveur |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_SSH_KEY` | le contenu de la clé **privée** `companero-deploy` (à effacer du serveur ensuite) |
| `DEPLOY_PORT` | facultatif, 22 par défaut |
| `DEPLOY_KNOWN_HOSTS` | facultatif mais conseillé : la sortie de `ssh-keyscan` ; sinon la clé du serveur est acceptée au premier contact |

Et en *Variables* : `DEPLOY_RUN_AS` = `sudo -u www-data` (à laisser vide si l'utilisateur SSH est
déjà celui qui fait tourner PHP), et `DEPLOY_PATH` si l'appli n'est pas dans `/srv/companero`
(penser alors à la règle sudoers).

Tant que ces secrets manquent, le workflow teste mais ne déploie pas (un avertissement le
rappelle). Un déploiement se relance à la main depuis l'onglet *Actions* (« Run workflow »
sur `main`), en cochant au besoin « Recompiler et publier l'appli Android ».

## L'appli Android

À chaque push sur `main`, la CI regarde si le dossier `android/` a changé depuis l'appli publiée
(elle lit `https://votre-domaine/companero-apk.json`). Si oui, elle compile une nouvelle version
(son numéro : le nombre de commits, toujours croissant), la signe avec **toujours la même clé**,
et `deploy.sh` la dépose dans `public/` avec sa description (`companero-apk.json` : version,
empreinte SHA-256). Le lien de téléchargement, `https://votre-domaine/companero.apk`, donne
donc toujours la dernière version.

**Les téléphones se mettent à jour tout seuls.** À chaque retour dans l'appli (au plus toutes les
30 minutes), elle regarde la description sur le serveur ; si une version plus récente existe, elle
la télécharge, vérifie son empreinte et l'installe **par-dessus elle-même** : rien à désinstaller,
rien de perdu (la session reste ouverte).

- La toute première fois, Android demande d'autoriser Companero à installer des applis (un
  interrupteur dans les réglages), puis de confirmer la mise à jour.
- Ensuite, sur Android 12 et plus, les mises à jour s'installent **sans rien demander**, au moment
  où l'on quitte l'appli. Sur Android 9 à 11, une fenêtre propose « Mettre à jour » à chaque fois.

### À configurer une fois

Le workflow qui fait tout ça est dans [`deploy/github-ci.yml`](../deploy/github-ci.yml) : copiez-le
à la place de `.github/workflows/ci.yml` (un accès GitHub sans le droit « workflow », comme celui
de Claude, ne peut pas modifier ce dossier lui-même).

Dans GitHub, *Settings › Secrets and variables › Actions* :

| Où | Nom | Valeur |
|---|---|---|
| *Variables* | `APP_URL` | l'adresse publique, sans `/` final : `https://votre-domaine`. C'est celle que l'appli ouvre, et où elle cherche ses mises à jour. |
| *Secrets* | `ANDROID_KEYSTORE_BASE64` | la clé de signature, en base64 (voir ci-dessous) |
| *Secrets* | `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD` | seulement pour une clé à vous ; pour la clé de debug, les valeurs par défaut (`android`, `androiddebugkey`, `android`) conviennent |

**La clé.** Android n'installe une mise à jour par-dessus une appli que si elle est signée avec la
même clé. Les APK compilées jusqu'ici avec `.\dev apk` sont signées avec la clé de debug du PC :
en la donnant à la CI, les téléphones déjà équipés n'ont **rien à désinstaller**. Sous Windows,
elle est dans `%USERPROFILE%\.android\debug.keystore` ; pour la copier en base64 :

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("$env:USERPROFILE\.android\debug.keystore")) | Set-Clipboard
```

Gardez ce fichier précieusement : sans lui, plus de mise à jour possible sans désinstaller.
(Une clé neuve, faite avec `keytool -genkeypair -v -keystore companero.keystore -alias companero
-keyalg RSA -keysize 2048 -validity 10000`, marche aussi, avec ses trois secrets — mais il faudra
alors désinstaller une dernière fois l'appli compilée sur le PC.)

Sans `APP_URL` ou sans la clé, la CI compile l'appli pour vérifier qu'elle se construit, mais ne
la publie pas (un avertissement le rappelle).

**Le premier passage.** Les téléphones qui ont une version d'avant ce mécanisme ne savent pas
encore se mettre à jour : installez-y une fois la nouvelle version depuis
`https://votre-domaine/companero.apk` — par-dessus l'ancienne, sans désinstaller. Ensuite, c'est
automatique.

Une APK compilée sur le PC (`.\dev apk`) a le même numéro que celle de la CI pour un même commit :
elles s'installent l'une sur l'autre tant qu'elles sont signées par la même clé.

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
