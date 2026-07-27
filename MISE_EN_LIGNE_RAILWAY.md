# cOllect_Pay — Déploiement Railway

## Contenu ajouté

- `Dockerfile` PHP 8.3 + Apache
- extensions PDO MySQL, MySQLi, GD, mbstring, ZIP et Intl
- Apache compatible avec les liens historiques `/collect_pay/...`
- connexion MySQL via variables Railway
- URL publique configurable avec `APP_URL`
- endpoint de santé `/health.php`

## Variables Railway à créer dans le service PHP

Relier d'abord un service MySQL Railway, puis ajouter :

```text
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
APP_ENV=production
APP_URL=https://VOTRE-DOMAINE.up.railway.app/collect_pay
QR_SECRET_KEY=UNE_CLE_LONGUE_ET_ALEATOIRE
QR_ENCRYPTION_KEY=UNE_CLE_DE_32_CARACTERES_EXACTEMENT
```

Le nom `MySQL` dans les références doit correspondre au nom réel du service MySQL dans Railway.

## Importation de la base

Depuis la console du service MySQL :

```bash
mysql -u "$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" < collect_pay.sql
```

Dans l'interface Railway, le fichier SQL doit d'abord être rendu accessible ou importé depuis votre PC avec les informations publiques du service MySQL.

## Déploiement GitHub

```bash
git init
git add .
git commit -m "Preparation cOllect_Pay pour Railway"
git branch -M main
git remote add origin URL_DU_DEPOT_GITHUB
git push -u origin main
```

Dans Railway : **New Project → Deploy from GitHub repo**, puis ajouter MySQL et les variables.

## URL de test

```text
https://VOTRE-DOMAINE.up.railway.app/collect_pay/login.php
```

## Données téléversées

Les dossiers `uploads/`, `assets/uploads/` et `assets/qr_codes/` sont inscriptibles. Le stockage local Railway est éphémère. Pour conserver les fichiers après redéploiement, monter un **Railway Volume** sur :

```text
/var/www/html/collect_pay/uploads
```

Pour un test initial, le déploiement fonctionne sans Volume, mais les fichiers téléversés peuvent disparaître lors d'un redéploiement.

## Sécurité

Le dump SQL fourni contient des données de recettes publiques. Pour une démonstration externe, importer de préférence une copie anonymisée et changer tous les mots de passe, secrets QR et identifiants administrateurs.
