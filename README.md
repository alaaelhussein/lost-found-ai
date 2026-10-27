# Lost&Found AI

Sur un campus ou dans une bibliothèque, un objet perdu et l'annonce qui le concerne ne se croisent presque jamais : les gens postent sur des groupes Facebook différents, personne ne recoupe les deux. C'est le problème de départ.

Lost&Found AI est une plateforme où on déclare un objet perdu ou trouvé (photo, lieu, description), et où l'application calcule elle-même un score de correspondance entre les annonces. Le contact entre les deux personnes passe par une messagerie interne : personne ne voit jamais l'email ou le numéro de l'autre.

> Exemple : « J'ai perdu mon sac noir à la bibliothèque » + « J'ai trouvé un sac noir à la bibliothèque » → correspondance à 92 %

Projet personnel, développé en solo par Alaa EL HUSSEIN dans le cadre d'une recherche de stage, en PHP 8 sans framework, MySQL et JavaScript vanilla.

---

## Sommaire

1. [État d'avancement](#1-état-davancement)
2. [Installation pas à pas](#2-installation-pas-à-pas)
3. [Architecture du code](#3-architecture-du-code)
4. [Le parcours d'une requête](#4-le-parcours-dune-requête)
5. [Authentification en détail](#5-authentification-en-détail)
6. [Sécurité](#6-sécurité)
7. [Base de données](#7-base-de-données)
8. [Ajouter une nouvelle page (guide)](#8-ajouter-une-nouvelle-page-guide)
9. [Tests manuels](#9-tests-manuels)
10. [Dépannage](#10-dépannage)
11. [Algorithme de correspondance](#11-algorithme-de-correspondance)
12. [Conventions et planning](#12-conventions-et-planning)
13. [Emails, restitution et livraison](#13-emails-restitution-et-livraison)
14. [Compte, RGPD, modération et mise en ligne](#14-compte-rgpd-modération-et-mise-en-ligne)

> **Mettre le site en ligne :** suivre [DEPLOIEMENT.md](DEPLOIEMENT.md) (liste de tout ce qui reste à faire, dans l'ordre).

---

## 1. État d'avancement

### MVP
- [x] Structure du projet et schéma de la base de données
- [x] Inscription / connexion / déconnexion
- [x] Déclarer un objet **perdu** ou **trouvé** (titre, description, catégorie, couleur, lieu, date, photo)
- [x] Liste et recherche d'annonces avec filtres (type, catégorie, lieu, date)
- [x] Calcul du **score de correspondance** entre une annonce perdue et les annonces trouvées
- [x] Page « Mes annonces » avec gestion des annonces
- [x] Messagerie interne sécurisée entre les deux utilisateurs
- [x] Marquer une annonce comme « récupéré / résolu »

### Phase 2
- [ ] Analyse d'image par IA (détection de l'objet, couleur, marque)
- [ ] Carte interactive (Leaflet / OpenStreetMap)
- [x] Notifications (email) en cas de nouvelle correspondance
- [x] Vérification de l'email par code à 6 chiffres
- [x] Vérification du propriétaire (question de contrôle, détail non public, photo privée)
- [x] Livraison (point relais / casier / domicile) + pourboire, paiement simulé ou Stripe
- [ ] Versement réel des pourboires (Stripe Connect) et intégration d'un vrai transporteur (Boxtal / Sendcloud)
- [x] Espace administrateur / modération (signalements, `/admin/reports`)

---

## 2. Installation pas à pas

La méthode recommandée est **Docker** : il télécharge et installe tout seul
PHP 8.2 + Apache, MariaDB 10.6 et Mailpit (boîte mail de test). Rien d'autre à
installer sur Windows. L'explication complète de l'architecture Docker est dans
[`DOCKER.md`](DOCKER.md).

### 2.1 À installer une seule fois

1. **Docker Desktop** : https://www.docker.com/products/docker-desktop/
   Pendant l'installation, garder l'option **WSL 2** cochée, puis redémarrer le PC
   si c'est demandé. Activer la virtualisation dans le BIOS/UEFI si Docker la réclame.
2. **Ouvrir Docker Desktop** et attendre que la baleine en bas à gauche soit
   verte (« Engine running »).
3. **VS Code** (ou l'éditeur de votre choix).

### 2.2 Récupérer le projet

Dézipper `lost-found-ai.zip` (ou cloner le dépôt GitHub), par exemple dans
`C:\Users\<votre-nom>\lost-found-ai`, puis ouvrir ce dossier dans VS Code.

Si `config/config.php` n'existe pas (cas d'un `git clone`, car ce fichier n'est
pas versionné), le créer à partir du modèle :

```powershell
copy config\config.example.php config\config.php
```

puis y renseigner une clé `app.key` (64 caractères hexadécimaux) générée avec
`php -r "echo bin2hex(random_bytes(32));"`. Sans PHP installé sur Windows, la
générer après le démarrage, avec
`docker compose exec app php -r "echo bin2hex(random_bytes(32));"`.

### 2.3 Démarrer (terminal PowerShell)

```powershell
# 1. Aller dans le dossier du projet (adapter le chemin)
cd C:\Users\<votre-nom>\lost-found-ai

# 2. Vérifier que Docker tourne : les sections "Client" ET "Server" doivent s'afficher
docker version

# 3. Télécharger les images, construire et démarrer les 3 conteneurs
docker compose up -d --build
```

La commande 3 :
- télécharge les images **PHP 8.2 + Apache**, **MariaDB 10.6** et **Mailpit** ;
- construit l'image du site (`Dockerfile`) ;
- crée la base de données à partir de `database/schema.sql` (au tout premier démarrage).

La première fois, cela prend **2 à 5 minutes**, ensuite quelques secondes.

### 2.4 Vérifier

```powershell
docker compose ps
```

Les 3 services `app`, `db` et `mailpit` doivent être **Up**. Ouvrir ensuite :

| Adresse | Rôle |
|---|---|
| http://localhost:8000 | le site |
| http://localhost:8025 | Mailpit : tous les emails envoyés par le site (codes de vérification, mot de passe oublié...). Rien ne part sur Internet |

Pour devenir administrateur (lien « Modération »), créer d'abord son compte sur
le site, puis :

```powershell
docker compose exec app php bin/make-admin.php votre-email@exemple.fr
```

### 2.5 Au quotidien

```powershell
docker compose up -d          # démarrer
docker compose down           # arrêter (les données sont conservées)
docker compose down -v        # arrêter ET supprimer la base locale (repartir de zéro)
docker compose logs -f app    # voir les erreurs PHP (Ctrl+C pour quitter)
docker compose up -d --build  # après une modification du Dockerfile
```

Le volume `db_data` conserve les données entre deux arrêts : `schema.sql` n'est
exécuté automatiquement qu'à la création de ce volume. Pour une base existante,
appliquer les fichiers de `database/migrations/`, ou repartir de zéro avec
`docker compose down -v`.

Dans le conteneur PHP, l'hôte MySQL est `db` (et non `localhost`) :
`config/config.php` lit automatiquement les variables `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER` et `DB_PASSWORD` définies dans `docker-compose.yml`.

### 2.6 Si ça bloque

| Problème | Solution |
|---|---|
| `docker version` n'affiche que « Client » | Docker Desktop n'est pas démarré : l'ouvrir et attendre |
| « port is already allocated » | un autre programme utilise le port `8000` ou `3306` (souvent XAMPP / MySQL) : l'arrêter |
| Erreur de base de données juste après le démarrage | MariaDB s'initialise encore : attendre 30 secondes et recharger |

### 2.7 Alternative sans Docker : XAMPP

Prérequis : PHP **8.2 ou plus** (extensions `pdo_mysql`, `mbstring`, `fileinfo`,
`gd`, `sodium`) et MySQL 8 / MariaDB 10.4+, fournis par XAMPP.

1. **Démarrer MySQL** dans le panneau de contrôle XAMPP (*Start* à côté de *MySQL*).
2. **Créer la configuration locale** : `copy config\config.example.php config\config.php`.
   Par défaut XAMPP utilise `root` sans mot de passe ; sinon, renseigner `'password' => '...'`.
3. **Créer la base et les tables** :
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   ```
   > Attention : ce script **supprime** les tables existantes avant de les recréer.
4. **Lancer le serveur PHP** :
   ```powershell
   C:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
   ```
   `-t public` : seul `public/` est accessible ; `public/index.php` à la fin :
   toutes les URL sont envoyées au routeur.
5. Ouvrir **http://localhost:8000**.

---

## 3. Architecture du code

L'application suit une architecture **MVC** (Modèle, Vue, Contrôleur) écrite à la main, sans framework, pour que chaque mécanisme reste visible et compréhensible.

```
lost-found-ai/
├── public/                      ← SEUL dossier accessible depuis le web
│   ├── index.php                  Point d'entrée unique + déclaration des routes
│   ├── .htaccess                  Redirige toutes les URL vers index.php (Apache)
│   ├── css/style.css              Styles (variables de couleurs en haut du fichier)
│   ├── js/app.js                  JavaScript vanilla (ex. bouton « Afficher » du mot de passe)
│   └── uploads/                   Photos des annonces (exécution de scripts interdite)
│
├── src/                         ← Code de l'application (namespace PHP App\)
│   ├── bootstrap.php              Démarrage : autoloader, config, erreurs, session
│   ├── helpers.php                Fonctions globales : e(), redirect(), client_ip()
│   │
│   ├── Core/                      « Mini-framework » maison, réutilisé par toutes les pages
│   │   ├── Router.php               URL + méthode HTTP → méthode de contrôleur
│   │   ├── View.php                 Affiche une vue dans le layout commun
│   │   ├── Config.php               Lecture de config/config.php : Config::get('db.host')
│   │   ├── Auth.php                 Utilisateur connecté, login/logout, pages protégées
│   │   ├── Csrf.php                 Jeton anti-CSRF des formulaires
│   │   └── Flash.php                Messages affichés une seule fois après une redirection
│   │
│   ├── Controllers/               Reçoivent la requête, valident, appellent les modèles, choisissent la vue
│   │   ├── HomeController.php
│   │   ├── AuthController.php       Inscription, connexion, déconnexion
│   │   └── ItemController.php       Création des annonces perdues et trouvées
│   │
│   ├── Models/                    SEULS endroits où l'on écrit du SQL
│   │   ├── User.php                 Table users
│   │   ├── LoginAttempt.php         Table login_attempts (anti brute-force)
│   │   └── Item.php                 Création des annonces
│   │
│   ├── Services/                  Logique métier complexe
│   │   └── MatchingService.php      Score de correspondance perdu / trouvé (sur 100)
│   │
│   └── Views/                     Gabarits HTML (PHP), sans logique métier
│       ├── layout.php               Structure commune : <head>, menu, messages flash, pied de page
│       ├── home.php
│       ├── auth/login.php
│       ├── auth/register.php
│       └── errors/error.php         Page d'erreur générique (403, 404, 405, 500...)
│
├── config/
│   ├── config.example.php         Modèle de configuration (versionné)
│   ├── config.php                 Configuration locale avec secrets (NON versionné)
│   └── database.php               Fonction db() : connexion PDO unique
│
├── database/
│   ├── schema.sql                 Création de la base et des tables
│   └── migrations/                Mises à jour d'une base existante (sans perte de données)
│
├── bin/
│   └── rebuild-matches.php        Recalcul de toutes les correspondances (ligne de commande)
│
├── tests/
│   └── MatchingServiceTest.php    Tests de l'algorithme de correspondance
│
├── README.md
└── .gitignore
```

### Rôle de chaque couche

| Couche | Fait | Ne fait jamais |
|---|---|---|
| **Contrôleur** | lit `$_POST`/`$_GET`, valide les données, vérifie les droits, appelle les modèles, choisit la vue ou redirige | écrire du SQL, produire du HTML |
| **Modèle** | exécute des requêtes SQL préparées et retourne des tableaux | lire `$_POST`, afficher quoi que ce soit |
| **Vue** | affiche les variables reçues, toujours échappées avec `e()` | accéder à la base, modifier des données |
| **Core** | fournit les outils communs (routage, session, sécurité) | contenir de la logique propre à Lost&Found |

### Pourquoi `public/` est séparé

Le serveur web ne donne accès **qu'à** `public/`. Les fichiers `config/config.php` (mot de passe de la base), `src/` et `database/` sont donc inaccessibles depuis un navigateur, même en tapant leur URL.

### Autoloader

Il n'y a aucun `require` de classe dans le code. `src/bootstrap.php` enregistre un autoloader qui traduit le nom d'une classe en chemin de fichier :

```
App\Controllers\AuthController  →  src/Controllers/AuthController.php
App\Core\Router                 →  src/Core/Router.php
```

Pour créer une classe, il suffit donc de respecter cette correspondance namespace ↔ dossier.

---

## 4. Le parcours d'une requête

Exemple : l'utilisateur envoie le formulaire de connexion (`POST /login`).

```
Navigateur
   │  POST /login  (email, password, _csrf)
   ▼
public/index.php ─────────── point d'entrée unique
   │  require src/bootstrap.php
   │     ├─ autoloader
   │     ├─ chargement de config/config.php
   │     ├─ gestionnaire d'erreurs (page 500)
   │     ├─ en-têtes de sécurité HTTP
   │     └─ session_start() avec cookie sécurisé
   │
   │  $router->dispatch('POST', '/login')
   ▼
Router ── trouve la route ['POST', '/login'] → AuthController::login()
   ▼
AuthController::login()
   │  1. Csrf::verify()                 jeton valide ?           sinon → 403
   │  2. LoginAttempt::isBlocked()      trop d'échecs ?          sinon → 429
   │  3. User::findByEmail()            SQL préparé
   │  4. password_verify()              mot de passe correct ?   sinon → 401 + formulaire
   │  5. Auth::login($user)             nouvel id de session + user_id en session
   │  6. Flash::add('success', ...)
   │  7. redirect('/')                  réponse 303
   ▼
Navigateur ── suit la redirection → GET /
   ▼
HomeController::index() → View::render('home')
   │  home.php est rendu, puis inséré dans layout.php
   │  layout.php affiche le menu « Bonjour, Alaa » et le message flash
   ▼
Page HTML envoyée
```

Après un formulaire **réussi**, on redirige toujours (modèle *Post/Redirect/Get*) : si l'utilisateur rafraîchit la page, le formulaire n'est pas renvoyé une deuxième fois.
Après un formulaire **en erreur**, on réaffiche directement le formulaire avec les messages d'erreur et les valeurs déjà saisies.

---

## 5. Authentification en détail

### Routes

| Méthode | URL | Contrôleur | Accès |
|---|---|---|---|
| GET | `/` | `HomeController::index` | tous |
| GET | `/items` | `ItemController::index` | tous |
| GET | `/items/{id}` | `ItemController::show` | tous |
| GET | `/my-items` | `ItemController::mine` | utilisateurs connectés |
| GET | `/conversations` | `ConversationController::index` | utilisateurs connectés |
| GET | `/conversations/{id}` | `ConversationController::show` | participants uniquement |
| POST | `/conversations/{id}/messages` | `ConversationController::send` | participants + CSRF |
| GET | `/items/create?type=lost\|found` | `ItemController::showCreate` | utilisateurs connectés |
| POST | `/items/create` | `ItemController::create` | utilisateurs connectés |
| POST | `/items/{id}/status` | `ItemController::updateStatus` | propriétaire + CSRF |
| POST | `/items/{id}/delete` | `ItemController::delete` | propriétaire + CSRF |
| GET | `/register` | `AuthController::showRegister` | visiteurs non connectés |
| POST | `/register` | `AuthController::register` | visiteurs non connectés |
| GET | `/login` | `AuthController::showLogin` | visiteurs non connectés |
| POST | `/login` | `AuthController::login` | visiteurs non connectés |
| POST | `/logout` | `AuthController::logout` | tous (protégé par CSRF) |

Un utilisateur déjà connecté qui ouvre `/login` ou `/register` est renvoyé à l'accueil (`Auth::requireGuest()`).

### Contact sécurisé entre deux utilisateurs

Depuis le détail d'une annonce, un utilisateur connecté peut démarrer
directement une conversation privée avec son auteur. Il n'est pas nécessaire
de publier d'abord une annonce complémentaire. L'application contrôle que
l'utilisateur ne contacte pas sa propre annonce et que l'annonce est encore
ouverte.

Si une annonce complémentaire existe, la correspondance peut ensuite être
associée aux deux annonces. Les emails et numéros ne sont jamais affichés.
Les participants retrouvent leurs échanges dans `/conversations`.

### Inscription

Règles de validation (dans `AuthController::validateRegistration`) :

| Champ | Règle |
|---|---|
| Nom | 2 à 100 caractères |
| Email | format valide, 190 caractères max, pas déjà utilisé, **enregistré en minuscules** |
| Mot de passe | 8 caractères minimum, au moins une lettre et un chiffre, 72 octets max (limite de bcrypt) |
| Confirmation | identique au mot de passe |

Le mot de passe est stocké uniquement sous forme de hash (`password_hash`, algorithme bcrypt). Après l'inscription, l'utilisateur est connecté automatiquement.

### Connexion

1. Si l'email a eu **5 échecs** ou l'IP **20 échecs** dans les 15 dernières minutes → refus (code 429).
2. Le mot de passe est vérifié avec `password_verify`.
3. En cas d'échec, le message est toujours **« Email ou mot de passe incorrect »**, que l'email existe ou non, pour ne pas révéler quels emails sont inscrits.
4. En cas de succès : les échecs sont effacés, l'identifiant de session est renouvelé, puis l'utilisateur est redirigé vers la page qu'il voulait voir (ou l'accueil).

### Ce qui est stocké en session

| Clé | Contenu |
|---|---|
| `user_id` | id de l'utilisateur connecté (rien d'autre : nom, rôle... sont relus en base) |
| `_csrf_token` | jeton anti-CSRF |
| `_flash` | messages à afficher sur la page suivante |
| `_intended_url` | page demandée avant d'être envoyé vers `/login` |

### Protéger une page (étapes suivantes)

```php
public function create(): void
{
    Auth::requireLogin();          // non connecté → redirigé vers /login, puis ramené ici
    $userId = Auth::id();          // id de l'utilisateur connecté
    $user   = Auth::user();        // ['id', 'name', 'email', 'role', 'created_at']
    // ...
}
```

---

## 6. Sécurité

| Menace | Protection | Où |
|---|---|---|
| Injection SQL | requêtes préparées PDO uniquement, préparation réelle côté MySQL (`ATTR_EMULATE_PREPARES = false`) | `config/database.php`, `src/Models/` |
| XSS (injection de JavaScript) | toute donnée affichée passe par `e()` (`htmlspecialchars`) | `src/helpers.php`, toutes les vues |
| CSRF (formulaire forgé par un autre site) | jeton secret dans chaque formulaire POST + cookie `SameSite=Lax` | `src/Core/Csrf.php` |
| Vol de mot de passe en base | hash bcrypt avec sel (`password_hash`), mise à jour automatique si l'algorithme évolue | `AuthController` |
| Brute force | 5 échecs par email / 20 par IP sur 15 min, stockés en base | `src/Models/LoginAttempt.php` |
| Découverte des emails inscrits | message d'erreur identique + temps de réponse identique (hash factice) | `AuthController::login` |
| Fixation de session | `session_regenerate_id(true)` à la connexion et à la déconnexion, `use_strict_mode` | `src/Core/Auth.php`, `bootstrap.php` |
| Vol de cookie de session | cookie `HttpOnly`, `Secure` en HTTPS, jamais dans l'URL | `src/bootstrap.php` |
| Clickjacking | en-tête `X-Frame-Options: DENY` | `src/bootstrap.php` |
| Redirection vers un site externe | l'URL de retour après connexion doit être un chemin interne | `Auth::pullIntendedUrl` |
| Fuite d'informations techniques | détails d'erreur affichés uniquement si `app.debug = true` | `src/bootstrap.php` |
| Script envoyé à la place d'une photo | type réel vérifié (`finfo` + `getimagesize`), nom aléatoire, seuls `.jpg/.png/.webp` servis, PHP désactivé et `CSP sandbox` dans `uploads/` | `ItemController::storePhoto`, `public/uploads/.htaccess` |
| Fuite de localisation par la photo | image réencodée avec GD : métadonnées EXIF/GPS supprimées, redimensionnée à 1600 px | `ItemController::reencodePhoto` |
| Image « bombe » (énorme une fois décodée) | dimensions lues dans l'en-tête et limitées à 6000 px avant décodage | `ItemController::storePhoto` |
| Photo encore en ligne après suppression | le fichier est effacé avec l'annonce | `ItemController::deletePhoto` |
| XSS (défense en profondeur) | `Content-Security-Policy` stricte : aucun script inline, seuls nos fichiers `/js` s'exécutent | `src/bootstrap.php`, `public/js/app.js` (attributs `data-*`) |
| Spam / abus | limites de débit en base : 5 inscriptions/h par IP, 20 messages/5 min, 10 contacts/h, 10 annonces/h par compte (réponse 429) | `src/Core/Throttle.php`, `src/Models/RateLimit.php` |
| Redirection vers un site externe (variantes) | refus de `//`, `/\` et des caractères de contrôle dans l'URL de retour | `Auth::pullIntendedUrl` |
| Session oubliée sur un poste partagé | déconnexion après 2 h d'inactivité ; nouveau jeton CSRF à la connexion | `Auth::enforceIdleTimeout`, `Csrf::rotate` |
| Pages privées relues depuis le cache | `Cache-Control: no-store` (bouton « Précédent » après déconnexion) | `src/bootstrap.php` |
| Interception en HTTPS | `Strict-Transport-Security` envoyé en HTTPS | `src/bootstrap.php` |
| Données mal encodées (erreur 500) | tout texte reçu doit être de l'UTF-8 valide, sinon 400 | `src/bootstrap.php` |
| Accès aux secrets | seul `public/` est exposé ; `config.php` exclu de Git | structure, `.gitignore` |
| Base de dev exposée sur le réseau | ports Docker liés à `127.0.0.1` ; versions Apache/PHP masquées | `docker-compose.yml`, `Dockerfile` |

**Mise à jour d'une base existante** (sans perte de données) : `mysql -u root lost_found_ai < database/migrations/2026_10_15_add_rate_limits.sql`.

**Extension GD** : nécessaire pour effacer les métadonnées GPS des photos. Elle est incluse dans l'image Docker ; avec XAMPP, décommenter `extension=gd` dans `php.ini`. Sans GD, la photo est conservée telle quelle (un avertissement est écrit dans le log et le formulaire prévient l'utilisateur).

**Avant la mise en production** : passer `app.debug` à `false`, utiliser HTTPS et créer un utilisateur MySQL dédié avec un mot de passe (pas `root`).

---

## 7. Base de données

Fichier : [`database/schema.sql`](database/schema.sql). Moteur InnoDB (clés étrangères et transactions), encodage `utf8mb4` (tous les caractères, y compris les emojis).

```
users ──< items ──< matches >── items
  │                    │
  │                    └──< conversations ──< messages >── users (sender)
  │
login_attempts (indépendante)
```

| Table | Rôle | Points importants |
|---|---|---|
| `users` | comptes | `email` unique, `role` = `user` ou `admin` |
| `login_attempts` | échecs de connexion | une ligne par échec, purgée après 24 h |
| `items` | annonces | `type` = `lost`/`found`, `status` = `open`/`matched`/`resolved`, coordonnées GPS optionnelles, `verification_question` pour la phase 2 |
| `matches` | correspondances | couple (perdu, trouvé) unique, `score` sur 100 |
| `conversations` | une par correspondance | `match_id` unique |
| `messages` | messagerie | `read_at` = date de lecture (NULL = non lu) |

**Suppression en cascade** : supprimer un utilisateur supprime ses annonces, qui suppriment leurs correspondances, conversations et messages.

---

## 8. Ajouter une nouvelle page (guide)

Exemple : une page « Mes annonces » sur `/my-items`.

**1. Déclarer la route** dans `public/index.php` :
```php
$router->get('/my-items', [ItemController::class, 'mine']);
```

**2. Écrire le modèle** `src/Models/Item.php` (seul endroit où l'on écrit du SQL) :
```php
public function findByUser(int $userId): array
{
    $stmt = $this->db->prepare('SELECT * FROM items WHERE user_id = :user_id ORDER BY created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}
```

**3. Écrire le contrôleur** `src/Controllers/ItemController.php` :
```php
public function mine(): void
{
    Auth::requireLogin();
    View::render('items/mine', [
        'title' => 'Mes annonces',
        'items' => (new Item())->findByUser(Auth::id()),
    ]);
}
```

**4. Écrire la vue** `src/Views/items/mine.php`, en échappant chaque valeur :
```php
<?php foreach ($items as $item): ?>
    <h2><?= e($item['title']) ?></h2>
<?php endforeach; ?>
```

**Paramètres d'URL** : `$router->get('/items/{id}', [ItemController::class, 'show']);` appelle `show(int $id)`. Un paramètre `{...}` n'accepte que des chiffres.

**Formulaire POST** : ajouter `<?= Csrf::field() ?>` dans le `<form>` et appeler `Csrf::verify();` au début de l'action.

---

## 9. Tests manuels

Les tests ci-dessous ont été exécutés (avec `curl`) et passent tous :

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Ouvrir `/` | 200, page d'accueil |
| 2 | Ouvrir `/css/style.css` | 200, fichier servi directement |
| 3 | Ouvrir `/inconnu` | 404, page d'erreur |
| 4 | Ouvrir `/logout` en GET | 405 (la déconnexion exige un POST) |
| 5 | Envoyer un formulaire sans jeton CSRF | 403 |
| 6 | Inscription avec nom trop court, email invalide, mot de passe court | 422, un message sous chaque champ |
| 7 | Inscription valide | redirection vers `/`, « Bonjour, … » dans le menu |
| 8 | Ouvrir `/login` en étant connecté | redirection vers `/` |
| 9 | Déconnexion | redirection, message « Vous êtes déconnecté » |
| 10 | Inscription avec un email déjà utilisé | 422, « Un compte existe déjà avec cet email » |
| 11 | Connexion avec un mauvais mot de passe | 401, « Email ou mot de passe incorrect » |
| 12 | Connexion avec l'email en MAJUSCULES | connexion réussie (emails insensibles à la casse) |
| 13 | 5 échecs puis le bon mot de passe | 429, « Trop de tentatives » |
| 14 | Autre email depuis la même IP pendant le blocage | non bloqué |
| 15 | En-têtes de réponse | `X-Frame-Options: DENY`, `nosniff`, cookie `HttpOnly; SameSite=Lax` |

Pour débloquer un compte pendant le développement :
```sql
DELETE FROM login_attempts;
```

---

## 10. Dépannage

| Symptôme | Cause | Solution |
|---|---|---|
| « could not find driver » | l'extension `pdo_mysql` n'est pas activée | utiliser le PHP de XAMPP (`C:\xampp\php\php.exe`), ou décommenter `extension=pdo_mysql` dans le `php.ini` |
| « Connection refused » / « No connection could be made » | MySQL n'est pas démarré | *Start* sur MySQL dans le panneau XAMPP |
| « Unknown database 'lost_found_ai' » | schéma pas importé | `mysql -u root < database\schema.sql` |
| « Fichier de configuration manquant » | `config/config.php` absent | copier `config.example.php` en `config.php` |
| `/login` affiche une 404 du serveur PHP | serveur lancé sans `public/index.php` à la fin | `php -S localhost:8000 -t public public/index.php` |
| Erreur 403 « session expirée » | page ouverte depuis longtemps ou cookie supprimé | recharger la page et renvoyer le formulaire |
| « Trop de tentatives » en développement | limite anti brute-force atteinte | `DELETE FROM login_attempts;` |

---

## 11. Algorithme de correspondance

Fichier : [`src/Services/MatchingService.php`](src/Services/MatchingService.php). Algorithme classique en PHP, **sans IA** : chaque critère rapporte des points.

| Critère | Points | Détail |
|---|---|---|
| Catégorie | 25 | identique ou synonyme = 25 ; mot en commun = 15 |
| Couleur | 20 | même famille (« argenté » = « gris ») = 20 ; couleur trouvée dans le titre = 16 ; inconnue = 6 |
| Lieu | 25 | similarité des mots du lieu ; distance GPS si les deux annonces ont des coordonnées (≤ 300 m = 25) |
| Date | 15 | trouvé le jour même ou le lendemain = 15, puis dégressif jusqu'à 30 jours |
| Mots-clés | 15 | part des mots du titre + description présents dans l'autre annonce |

**Règle éliminatoire** : un objet trouvé plus d'un jour *avant* la perte donne un score de 0.

**Préparation du texte** avant comparaison :
1. minuscules et suppression des accents (« Bibliothèque » → « bibliotheque ») ;
2. remplacement des expressions (« porte-monnaie » → « portefeuille », « sac à dos » → « sac ») ;
3. suppression des mots vides (« le », « dans », « perdu »…) ;
4. singulier simplifié (« clés » → « cle ») ;
5. synonymes et abréviations (« smartphone » → « telephone », « BU » → « bibliotheque »).

**Quand le calcul a lieu** :
- à la publication d'une annonce : l'auteur est redirigé vers sa page avec le nombre de correspondances trouvées ;
- à chaque visite de sa propre annonce : le calcul est relancé, pour tenir compte des annonces publiées entre-temps ;
- à la demande, pour toutes les annonces : `php bin/rebuild-matches.php`.

Les correspondances (score ≥ `matching.min_score`, 60 par défaut) sont enregistrées dans la table `matches`. Elles ne sont visibles que par l'auteur de l'annonce, avec le score et les raisons (« Même couleur », « Dates très proches »…). « Mes annonces » affiche un badge avec leur nombre.

**Tests** : `php tests/MatchingServiceTest.php` (ou `docker compose exec app php tests/MatchingServiceTest.php`). 23 cas : normalisation, synonymes, exemple du sac noir, règles de date, distance GPS.

- Phase 2 : similarité d'image et score sémantique via une API d'IA

### Contact sécurisé
- Les emails et numéros ne sont **jamais affichés** publiquement.
- Les échanges passent par la messagerie interne, rattachée à une correspondance (`match`).
- Question de vérification : la personne qui a trouvé l'objet peut cacher un détail que seul le vrai propriétaire connaît.

---

## 12. Conventions et planning

### Conventions
- Commentaires et textes en **français**, noms de variables, fonctions et classes en **anglais**.
- Chaque fichier commence par un commentaire expliquant son rôle.
- SQL uniquement dans `src/Models/`, toujours avec `prepare()` + `execute()`.
- Toute donnée affichée dans une vue passe par `e()`.
- Tout formulaire POST contient `Csrf::field()`, et son action appelle `Csrf::verify()`.
- Code compatible **PHP 8.0** (version fournie par XAMPP) : pas d'`enum`, de `readonly` ni de type de retour `never`.
- Ne jamais versionner `config/config.php` ni des clés d'API.

### Stack technique

| Couche | Technologie |
|---|---|
| Front | HTML5, CSS3, JavaScript vanilla |
| Back | PHP 8 (PDO, MVC maison) |
| Base de données | MySQL / MariaDB |
| IA | score textuel en PHP (MVP), API vision / LLM (phase 2) |
| Carte | Leaflet + OpenStreetMap (phase 2) |
| Maquettes | Figma |

### Planning indicatif (~10 semaines)

| Semaines | Objectif |
|---|---|
| 1 | Cadrage, maquettes Figma, schéma BDD |
| 2-3 | Authentification, CRUD des annonces, upload photo |
| 4 | Recherche et filtres |
| 5-6 | Algorithme de correspondance + page « Mes correspondances » |
| 7 | Messagerie sécurisée, statuts d'annonce |
| 8 | IA (image/texte) |
| 9 | Carte, notifications, vérification du propriétaire |
| 10 | Tests, correctifs, déploiement (Hostinger), présentation |

---

## 13. Emails, restitution et livraison

### Vue d'ensemble du parcours

```
Alice trouve un portefeuille                 Bob a perdu son porte-monnaie
        │ déclare « trouvé »                           │ déclare « perdu »
        │ + question de vérification                   │
        └──────────── MatchingService : 88 % ──────────┘
                              │ email « Une annonce correspond » (au premier publié)
                              ▼
            Bob : « C'est à moi » → réponse + détail secret + photo privée
                              │ email à Alice
                              ▼
            Alice compare avec l'objet en main → accepte
                              │ email à Bob
                              ▼
            Bob choisit : point relais / casier / domicile + transporteur
            Bob paie : livraison (ex. 4,49 €) + pourboire 5 € = 9,49 €
                              │ emails : étiquette à Alice, confirmation à Bob
                              ▼
            Alice imprime l'étiquette, dépose le colis → « J'ai déposé »
                              │ email à Bob (suivi)
                              ▼
            Bob reçoit l'objet → « J'ai bien reçu »
                              │ annonce résolue, pourboire disponible, email à Alice
```

### Vérification de l'email (code à 6 chiffres)

| Règle | Valeur | Où |
|---|---|---|
| Code | 6 chiffres aléatoires (`random_int`) | `EmailVerifier::sendCode` |
| Stockage | HMAC lié au compte, jamais en clair | table `email_verifications` |
| Validité | 15 minutes, 5 essais par code | `EmailVerifier` |
| Renvoi | au plus 1 par minute, 5 par heure | `VerificationController::resend` |
| Comptes non vérifiés | peuvent naviguer, mais pas publier, écrire, réclamer ni commander | `Auth::requireVerified()` |

### Envoi des emails

Classe [`src/Core/Mailer.php`](src/Core/Mailer.php) : petit client SMTP écrit à la main (pas de Composer), avec STARTTLS/SSL et authentification. Les gabarits sont dans `src/Views/emails/`, envoyés par [`src/Services/Notifier.php`](src/Services/Notifier.php).

| Environnement | Configuration | Où lire les emails |
|---|---|---|
| Docker (dev) | automatique (`MAIL_HOST=mailpit`) | **http://localhost:8025** |
| XAMPP sans serveur mail | `mail.transport = 'log'` | `storage/logs/mail.log` |
| Production | `MAIL_HOST`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, `MAIL_USERNAME`, `MAIL_PASSWORD` (Brevo, OVH…) | vraies boîtes mail |

Chaque email de correspondance contient un **lien de désinscription signé** (HMAC) : impossible de désinscrire quelqu'un d'autre en changeant l'id dans l'URL.

### Preuve de propriété

- À la déclaration d'un objet **trouvé**, l'auteur peut écrire une **question de vérification** (« Quelle carte de fidélité est dedans ? »). Elle n'est jamais affichée sur l'annonce publique.
- La personne qui réclame l'objet répond, décrit un **détail non visible** et peut joindre une **photo privée**, stockée dans `storage/proofs/` (hors de `public/`) et servie uniquement au demandeur et à l'auteur (`ClaimController::photo`).
- Une seule demande par personne et par objet, une seule demande acceptée par objet.

### Livraison et anonymat

| Mode | Ce que voit la personne qui expédie | Stockage de l'adresse |
|---|---|---|
| **Point relais** (Mondial Relay, Chronopost Shop2Shop, Colissimo) | prénom + point relais | aucune adresse |
| **Casier** (InPost, Mondial Relay Lockers) | prénom + casier | aucune adresse |
| **Domicile** (Colissimo, Chronopost) | nom + adresse (sur l'étiquette) | **chiffrée** (libsodium), effacée à la livraison |

Dans tous les cas, l'**expéditeur** imprimé est la plateforme (`delivery.sender`) : la personne qui a trouvé l'objet reste anonyme.

**Mode simulation** (`delivery.provider = 'simulation'`) : tarifs indicatifs, points relais fictifs, numéros de suivi fictifs. Pour la production, brancher un agrégateur (Boxtal, Sendcloud) dans `ShippingService::quotes()`, `relayPoints()` et `createShipment()` : le reste de l'application ne change pas.

### Paiement

| Mode | Activation | Fonctionnement |
|---|---|---|
| Simulation | aucune clé Stripe (par défaut) | bouton « Payer (simulation) », aucun argent débité |
| Stripe Checkout | `STRIPE_SECRET_KEY` + `STRIPE_WEBHOOK_SECRET` | page de paiement Stripe ; confirmation par **webhook signé** sur `/webhooks/stripe` |

Les montants sont toujours recalculés côté serveur (centimes, entiers) ; un montant envoyé par le navigateur est ignoré. Le webhook vérifie la signature, l'ancienneté (5 min), le statut « paid » **et** que le montant payé correspond à la commande.

> ⚠ **Avant d'encaisser de vrais pourboires** : reverser de l'argent à un tiers (la personne qui a trouvé l'objet) impose une solution de place de marché comme **Stripe Connect** (vérification d'identité des bénéficiaires, versements). Aujourd'hui, le pourboire est enregistré comme « disponible » (`orders.tip_status`) mais pas encore versé.

### Mettre à jour une base existante

```powershell
Get-Content database\migrations\2026_10_15_email_claims_orders.sql | docker exec -i lost-found-ai-db-1 mariadb -ulost_found -plost_found_password lost_found_ai
docker compose up -d --build   # ajoute Mailpit, GD, exif
```

Les comptes existants sont considérés comme **non vérifiés** : ils reçoivent un rappel et peuvent demander un code depuis `/verify-email`.

### Nouvelles routes

| Méthode | URL | Accès |
|---|---|---|
| GET/POST | `/verify-email` | connecté |
| POST | `/verify-email/resend` | connecté |
| GET/POST | `/notifications/unsubscribe` | lien signé |
| GET/POST | `/items/{id}/claim` | vérifié, pas l'auteur, objet trouvé ouvert |
| POST | `/claims/{id}/approve`, `/claims/{id}/reject` | auteur de l'annonce |
| GET | `/claims/{id}/photo` | demandeur ou auteur |
| GET/POST | `/claims/{id}/order` | demandeur d'une demande acceptée |
| GET | `/orders/{id}` | acheteur ou expéditeur |
| GET/POST | `/orders/{id}/pay` | acheteur (simulation) |
| GET | `/orders/{id}/label` | expéditeur, après paiement |
| POST | `/orders/{id}/shipped` | expéditeur |
| POST | `/orders/{id}/delivered` | acheteur |
| POST | `/webhooks/stripe` | Stripe (signature) |

---

## 14. Compte, RGPD, modération et mise en ligne

### Mot de passe oublié (`PasswordController`)

1. `/forgot-password` : l'utilisateur donne son email. **Réponse identique** que le compte existe ou non (on ne révèle pas qui est inscrit).
2. S'il existe, un lien `/reset-password?token=…` est envoyé. Le jeton fait 64 caractères aléatoires (`random_bytes`), seul son **HMAC** est en base (`password_resets`), il expire après **1 heure** et ne sert **qu'une fois**.
3. Le nouveau mot de passe incrémente `users.session_version` : **toutes les sessions ouvertes sont fermées** (si quelqu'un avait volé le compte, il est éjecté). `Auth::user()` compare la version en session à celle en base à chaque requête.

Les règles de mot de passe sont centralisées dans `Services/PasswordPolicy` (inscription, réinitialisation et changement utilisent la même fonction).

### Mon compte (`AccountController`, `/account`)

- **Changer de mot de passe** : redemande l'ancien ; les autres appareils sont déconnectés, la session en cours est conservée (`Auth::rememberSessionVersion`) ; un email d'alerte est envoyé.
- **Alertes** : activer/couper les emails de correspondance.
- **Exporter mes données** (RGPD art. 15 et 20) : fichier JSON (`User::exportData`).
- **Supprimer mon compte** (RGPD art. 17) : mot de passe + case à cocher. `AccountService` relève les photos **avant** le `DELETE` (les clés étrangères `ON DELETE CASCADE` effacent les données, mais pas les fichiers sur le disque), puis efface les photos publiques et les preuves privées. Bloqué si une livraison payée est en cours.

### Pages légales (`PageController`)

`/mentions-legales`, `/confidentialite`, `/cgu`. Les informations personnelles (éditeur, hébergeur, email de contact) viennent de la section `legal` de la configuration. Tant qu'une valeur est vide, la page affiche **[À COMPLÉTER]** en orange. L'inscription exige de cocher les CGU (date stockée dans `users.terms_accepted_at`).

### Signalement et modération

- Bouton **« Signaler cette annonce »** (repliable, en bas de chaque annonce) : motif + précisions. Un signalement par personne et par annonce (`reports`, clé unique). L'équipe reçoit un email (`legal.contact_email`).
- **`/admin/reports`** (administrateurs uniquement, 404 pour les autres) : conserver l'annonce, la supprimer, ou supprimer le compte de l'auteur.
- Devenir administrateur : `php bin/make-admin.php email@exemple.fr` (aucune page web ne permet de se donner ce rôle).

### Livraison activable

`delivery.enabled` (variable `DELIVERY_ENABLED`) active la livraison payante. **En production** (`app.debug = false`), `ShippingService::isEnabled()` la masque d'office tant que Stripe **et** un vrai transporteur ne sont pas branchés. Livraison masquée : à l'acceptation d'une demande, la conversation entre les deux personnes s'ouvre automatiquement pour organiser une **remise en main propre**.

### Nettoyage quotidien

`php bin/cleanup.php` (tâche cron) : codes et liens expirés, journaux de sécurité de plus de 24 h, comptes jamais vérifiés de plus de 7 jours. Ces durées sont celles annoncées dans la politique de confidentialité.

### Mettre à jour une base existante

```powershell
Get-Content database\migrations\2026_10_16_accounts_legal_reports.sql | docker exec -i lost-found-ai-db-1 mariadb -ulost_found -plost_found_password lost_found_ai
```

### Nouvelles routes

| Méthode | URL | Accès |
|---|---|---|
| GET/POST | `/forgot-password` | visiteur |
| GET/POST | `/reset-password` | lien reçu par email |
| GET | `/account` | connecté |
| POST | `/account/password`, `/account/notifications`, `/account/delete` | connecté |
| GET | `/account/export` | connecté |
| GET | `/mentions-legales`, `/confidentialite`, `/cgu` | public |
| POST | `/items/{id}/report` | vérifié, pas l'auteur |
| GET | `/admin/reports` | administrateur |
| POST | `/admin/reports/{id}/dismiss`, `/admin/items/{id}/delete`, `/admin/users/{id}/delete` | administrateur |

---

## Auteur

Projet développé en solo par Alaa EL HUSSEIN.

## Licence

Tous droits réservés. Ce code est public pour consultation (portfolio), mais ni la copie, ni la réutilisation, ni la distribution ne sont autorisées sans l'accord écrit de l'auteur.
