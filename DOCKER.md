# Utiliser Lost&Found AI avec Docker

Cette fiche explique comment installer, démarrer et comprendre
l'environnement local Docker du projet.

## 1. Ce que Docker remplace

Sans Docker, il faudrait installer directement sur Windows :

- PHP ;
- Apache ;
- MySQL ou MariaDB ;
- les extensions PHP nécessaires.

Avec Docker, ces composants sont isolés dans des conteneurs :

```text
Navigateur
    |
    | http://localhost:8000
    v
Conteneur app : PHP 8.2 + Apache
    |
    | réseau Docker interne, hôte = db
    v
Conteneur db : MariaDB 10.6
```

Le code du projet reste sur Windows et est monté dans le conteneur PHP.
Les données MariaDB sont conservées dans un volume Docker nommé `db_data`.

## 2. Prérequis Windows

Installer et démarrer :

1. Docker Desktop pour Windows ;
2. le moteur Linux de Docker Desktop ;
3. WSL 2 si Docker Desktop le demande.

Vérifier l'installation dans PowerShell :

```powershell
docker version
docker compose version
```

`docker version` doit afficher les sections `Client` et `Server`. Si seule la
section `Client` s'affiche, Docker Desktop ou son moteur n'est pas démarré.

Vérifier aussi que les ports suivants sont disponibles :

- `8000` pour l'application ;
- `3306` pour MariaDB.

XAMPP n'est pas nécessaire. Si MySQL de XAMPP utilise déjà le port `3306`,
arrêter MySQL dans XAMPP avant de démarrer Docker.

## 3. Les fichiers Docker du projet

### `Dockerfile`

Le [Dockerfile](./Dockerfile) décrit l'image de l'application :

1. part de l'image officielle `php:8.2-apache` ;
2. installe `libonig-dev`, nécessaire pour `mbstring` ;
3. installe les extensions PHP `pdo_mysql` et `mbstring` ;
4. active Apache `mod_rewrite` pour le routage via `.htaccess` ;
5. configure Apache pour exposer uniquement `public/` ;
6. prépare `public/uploads/` pour les photos.

La bibliothèque `libonig-dev` est indispensable. Sans elle, la construction
échoue avec l'erreur :

```text
Package 'oniguruma' ... not found
```

### `docker-compose.yml`

Le fichier [docker-compose.yml](./docker-compose.yml) démarre deux services :

| Service | Rôle | Nom réseau |
|---|---|---|
| `app` | PHP 8.2 + Apache | `app` |
| `db` | MariaDB 10.6 | `db` |

Le service `app` publie le port :

```text
Windows localhost:8000 -> Apache du conteneur:80
```

Le service `db` publie le port `3306` et utilise le volume `db_data`.

Le service `app` attend que MariaDB soit déclaré `healthy` avant de démarrer,
grâce au `healthcheck` de la base et à `depends_on`.

### `.dockerignore`

Le [.dockerignore](./.dockerignore) exclut notamment :

- `config/config.php` ;
- les logs ;
- `vendor/` et `node_modules/` ;
- les photos uploadées.

Cela évite de copier des secrets, des dépendances inutiles ou des fichiers
temporaires dans l'image Docker.

## 4. Configuration de la connexion à la base

Dans [config.php](./config/config.php), la connexion lit ces variables :

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
```

Docker Compose les fournit automatiquement au conteneur `app` :

```text
DB_HOST=db
DB_PORT=3306
DB_NAME=lost_found_ai
DB_USER=lost_found
DB_PASSWORD=lost_found_password
```

Point important : depuis le conteneur PHP, l'hôte de la base est `db`, car
`db` est le nom du service Docker. Il ne faut pas utiliser `127.0.0.1` ou
`localhost` pour cette connexion : cela désignerait le conteneur PHP lui-même.

Les valeurs par défaut de la configuration restent compatibles avec une
installation PHP locale classique hors Docker.

## 5. Premier démarrage

Ouvrir PowerShell et aller à la racine du projet :

```powershell
cd C:\Users\alaae\lost-found-ai
```

Construire l'image et démarrer les services :

```powershell
docker compose up --build -d
```

Cette commande :

1. télécharge les images nécessaires ;
2. construit l'image PHP/Apache ;
3. crée le réseau Docker du projet ;
4. crée le volume `db_data` ;
5. démarre MariaDB ;
6. attend que MariaDB soit prête ;
7. démarre Apache et PHP.

Au premier démarrage seulement, MariaDB exécute automatiquement :

```text
database/schema.sql
```

Ce script crée la base et les tables `users`, `items`, `matches`,
`conversations`, `messages` et `login_attempts`.

Ouvrir ensuite :

```text
http://localhost:8000
```

Pages utiles :

```text
http://localhost:8000/register
http://localhost:8000/login
```

## 6. Vérifier que tout fonctionne

Voir l'état des conteneurs :

```powershell
docker compose ps
```

Le résultat attendu est similaire à :

```text
app   Up
db    Up (healthy)
```

Tester la page d'accueil en GET :

```powershell
curl.exe -s -o NUL -w "%{http_code}" http://localhost:8000/
```

Résultat attendu :

```text
200
```

Tester les tables de la base :

```powershell
docker compose exec -T db mariadb -ulost_found -plost_found_password -D lost_found_ai -e "SHOW TABLES;"
```

## 7. Commandes quotidiennes

Démarrer les conteneurs déjà construits :

```powershell
docker compose up -d
```

Arrêter les conteneurs en conservant la base :

```powershell
docker compose down
```

Voir les logs en temps réel :

```powershell
docker compose logs -f
```

Voir uniquement les logs PHP/Apache :

```powershell
docker compose logs -f app
```

Voir uniquement les logs MariaDB :

```powershell
docker compose logs -f db
```

Reconstruire après une modification du [Dockerfile](./Dockerfile) ou de
[docker-compose.yml](./docker-compose.yml) :

```powershell
docker compose up --build -d
```

Entrer dans le conteneur PHP :

```powershell
docker compose exec app bash
```

Vérifier les extensions PHP depuis le conteneur :

```powershell
docker compose exec app php -m
```

## 8. Modifier le code

Le projet est monté avec cette liaison :

```text
C:\Users\alaae\lost-found-ai -> /var/www/html
```

Les fichiers modifiés sur Windows sont donc immédiatement visibles par
Apache/PHP. En général, il n'est pas nécessaire de reconstruire l'image après
une modification d'un fichier PHP, CSS ou JavaScript.

Il faut reconstruire si l'on modifie :

- le `Dockerfile` ;
- `docker-compose.yml` ;
- les extensions PHP installées ;
- la configuration Apache de l'image.

## 9. Base de données et réinitialisation

Le volume `db_data` conserve les données lorsque les conteneurs sont arrêtés.

Arrêter sans supprimer les données :

```powershell
docker compose down
```

Supprimer les conteneurs et le volume de base :

```powershell
docker compose down -v
```

Puis recréer l'environnement :

```powershell
docker compose up --build -d
```

Attention : `docker compose down -v` supprime les utilisateurs, annonces,
messages et toutes les autres données locales. Le fichier
`database/schema.sql` sera alors rejoué au prochain démarrage de MariaDB.

## 10. Problèmes fréquents

### Docker ne répond pas

Erreur typique :

```text
failed to connect to the docker API
```

Solution :

1. ouvrir Docker Desktop ;
2. attendre que le moteur Linux soit démarré ;
3. relancer `docker version`.

La présence des sections `Client` et `Server` confirme que le moteur répond.

### Le port 8000 est déjà utilisé

Modifier temporairement la partie `ports` du service `app` :

```yaml
ports:
  - "8001:80"
```

Puis ouvrir `http://localhost:8001`.

### Le port 3306 est déjà utilisé

Arrêter MySQL/MariaDB lancé par XAMPP ou un autre logiciel. Le port interne
Docker reste `3306`.

### La base ne semble pas se créer

Voir les logs :

```powershell
docker compose logs db
```

Si le volume existe déjà, `schema.sql` ne sera pas rejoué. Pour repartir de
zéro :

```powershell
docker compose down -v
docker compose up --build -d
```

### Erreur de connexion à MySQL

Vérifier que la configuration utilise :

```text
DB_HOST=db
```

et non `localhost` ou `127.0.0.1`.

### La page répond 405 avec `curl -I`

`curl -I` utilise la méthode HTTP `HEAD`. Le routeur actuel gère `GET`, mais
pas `HEAD`. Tester la page avec :

```powershell
curl.exe -s -o NUL -w "%{http_code}" http://localhost:8000/
```

## 11. Arrêt complet de la journée

Pour arrêter le projet sans perdre les données :

```powershell
cd C:\Users\alaae\lost-found-ai
docker compose down
```

Le lendemain :

```powershell
cd C:\Users\alaae\lost-found-ai
docker compose up -d
```

## 12. Différence avec Hostinger

Docker sert ici à développer localement avec un environnement maîtrisé.

Sur un hébergement Hostinger mutualisé classique, Docker n'est généralement
pas disponible. Il faudra alors utiliser directement :

- PHP fourni par Hostinger ;
- MySQL/MariaDB fourni par Hostinger ;
- Apache et le `.htaccess` du projet.

La logique PHP reste la même. Seules les valeurs de
[config.php](./config/config.php) devront être remplacées par les identifiants
de la base Hostinger, avec `app.debug` à `false` et HTTPS activé.
